<?php

namespace App\Services;

use App\Models\AffiliateAccount;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class AffiliateReferralTrackingService
{
    public const QUERY_PARAMETER = 'ref';

    public const SESSION_KEY = 'affiliate.referral_context';

    public const COOKIE_NAME = 'blendbeats_affiliate_referral';

    public const ATTRIBUTION_WINDOW_DAYS = 30;

    public function __construct(private readonly AffiliateFraudProtectionService $fraud)
    {
    }

    public function capture(Request $request): ?array
    {
        $rawCode = trim((string) $request->query(self::QUERY_PARAMETER, ''));

        if ($rawCode === '') {
            return null;
        }

        $referralCode = $this->findActiveReferralCode($rawCode);

        if (! $referralCode) {
            return null;
        }

        $visit = $this->recordVisit($request, $referralCode);
        $context = $this->contextFor($referralCode, $visit);

        $request->session()->put(self::SESSION_KEY, $context);
        Cookie::queue(
            self::COOKIE_NAME,
            $this->encodeContext($context),
            self::ATTRIBUTION_WINDOW_DAYS * 24 * 60,
            config('session.path', '/'),
            config('session.domain'),
            (bool) config('session.secure'),
            true,
            false,
            config('session.same_site', 'lax'),
        );

        return $context;
    }

    public function currentContext(Request $request): ?array
    {
        $sessionContext = $request->session()->get(self::SESSION_KEY);

        if (is_array($sessionContext) && $this->contextIsUsable($sessionContext)) {
            return $sessionContext;
        }

        $cookieContext = $this->decodeContext($request->cookie(self::COOKIE_NAME));

        if ($cookieContext && $this->contextIsUsable($cookieContext)) {
            $request->session()->put(self::SESSION_KEY, $cookieContext);

            return $cookieContext;
        }

        return null;
    }

    public function clearContext(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        Cookie::queue(Cookie::forget(
            self::COOKIE_NAME,
            config('session.path', '/'),
            config('session.domain'),
        ));
    }

    public function publicContext(?array $context): ?array
    {
        if (! $context) {
            return null;
        }

        return [
            'referral_code' => $context['referral_code'] ?? null,
            'affiliate_campaign_id' => $context['affiliate_campaign_id'] ?? null,
            'campaign_slug' => $context['campaign_slug'] ?? null,
            'referral_visit_id' => $context['referral_visit_id'] ?? null,
            'captured_at' => $context['captured_at'] ?? null,
            'expires_at' => $context['expires_at'] ?? null,
        ];
    }

    private function findActiveReferralCode(string $code): ?AffiliateReferralCode
    {
        return AffiliateReferralCode::query()
            ->with(['affiliateAccount', 'campaign'])
            ->whereRaw('LOWER(code) = ?', [Str::lower($code)])
            ->where('status', AffiliateReferralCode::STATUS_ACTIVE)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('affiliateAccount', function ($query): void {
                $query->where('status', AffiliateAccount::STATUS_ACTIVE);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('affiliate_campaign_id')
                    ->orWhereHas('campaign', fn ($query) => $this->activeCampaignQuery($query));
            })
            ->first();
    }

    private function recordVisit(Request $request, AffiliateReferralCode $referralCode): AffiliateReferralVisit
    {
        $visitorId = $request->session()->get('affiliate.visitor_id');

        if (! is_string($visitorId) || $visitorId === '') {
            $visitorId = (string) Str::uuid();
            $request->session()->put('affiliate.visitor_id', $visitorId);
        }

        $ipHash = $this->fraud->hashValue($request->ip());
        $userAgentHash = $this->fraud->hashValue($request->userAgent());
        $fraudAssessment = $this->fraud->assessVisit($referralCode, $visitorId, $ipHash, $userAgentHash);

        return AffiliateReferralVisit::query()->create([
            'affiliate_referral_code_id' => $referralCode->id,
            'affiliate_account_id' => $referralCode->affiliate_account_id,
            'affiliate_campaign_id' => $referralCode->affiliate_campaign_id,
            'visitor_id' => $visitorId,
            'landing_url' => $request->fullUrl(),
            'referrer_url' => $request->headers->get('referer'),
            'ip_hash' => $ipHash,
            'user_agent_hash' => $userAgentHash,
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'visited_at' => now(),
            'is_suspicious' => $fraudAssessment['is_suspicious'],
            'suspicious_reason' => $fraudAssessment['reason'],
            'suspicious_at' => $fraudAssessment['is_suspicious'] ? now() : null,
            'metadata' => [
                'query_parameter' => self::QUERY_PARAMETER,
                'fraud_flags' => $fraudAssessment['flags'],
            ],
        ]);
    }

    private function contextFor(AffiliateReferralCode $referralCode, AffiliateReferralVisit $visit): array
    {
        $capturedAt = now();

        return [
            'affiliate_account_id' => $referralCode->affiliate_account_id,
            'affiliate_campaign_id' => $referralCode->affiliate_campaign_id,
            'affiliate_referral_code_id' => $referralCode->id,
            'referral_visit_id' => $visit->id,
            'referral_code' => $referralCode->code,
            'campaign_slug' => $referralCode->campaign?->slug,
            'captured_at' => $capturedAt->toISOString(),
            'expires_at' => $capturedAt->copy()->addDays(self::ATTRIBUTION_WINDOW_DAYS)->toISOString(),
        ];
    }

    private function contextIsUsable(array $context): bool
    {
        $codeId = Arr::get($context, 'affiliate_referral_code_id');
        $visitId = Arr::get($context, 'referral_visit_id');
        $expiresAt = Arr::get($context, 'expires_at');

        if (! $codeId || ! $visitId || ! $expiresAt || now()->greaterThan(Carbon::parse($expiresAt))) {
            return false;
        }

        return AffiliateReferralCode::query()
            ->whereKey($codeId)
            ->where('status', AffiliateReferralCode::STATUS_ACTIVE)
            ->where(function ($query): void {
                $query
                    ->whereNull('affiliate_campaign_id')
                    ->orWhereHas('campaign', fn ($query) => $this->activeCampaignQuery($query));
            })
            ->exists()
            && AffiliateReferralVisit::query()->whereKey($visitId)->exists();
    }

    private function activeCampaignQuery($query): void
    {
        $query
            ->where('status', AffiliateCampaign::STATUS_ACTIVE)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    private function encodeContext(array $context): string
    {
        return base64_encode((string) json_encode($context));
    }

    private function decodeContext(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $decoded = json_decode((string) base64_decode($value, true), true);

        return is_array($decoded) ? $decoded : null;
    }

}
