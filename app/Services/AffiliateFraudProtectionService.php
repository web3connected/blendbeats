<?php

namespace App\Services;

use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\User;
use Illuminate\Http\Request;

class AffiliateFraudProtectionService
{
    public const REASON_SELF_REFERRAL = 'self_referral';

    public const REASON_DUPLICATE_ATTRIBUTION = 'duplicate_attribution';

    public const REASON_REPEATED_VISITOR = 'repeated_visitor';

    public const REASON_REPEATED_IP_USER_AGENT = 'repeated_ip_user_agent';

    public const REASON_SIGNUP_DEVICE_MISMATCH = 'signup_device_mismatch';

    public const REASON_SHARED_DEVICE_SIGNUPS = 'shared_device_signups';

    public const REASON_SUSPICIOUS_VISIT = 'suspicious_visit';

    private const VISITOR_VISIT_LIMIT = 3;

    private const DEVICE_VISIT_LIMIT = 5;

    public function assessVisit(
        AffiliateReferralCode $referralCode,
        ?string $visitorId,
        ?string $ipHash,
        ?string $userAgentHash,
    ): array {
        $flags = [];

        if ($visitorId) {
            $visitorVisitCount = AffiliateReferralVisit::query()
                ->where('affiliate_account_id', $referralCode->affiliate_account_id)
                ->where('visitor_id', $visitorId)
                ->where('visited_at', '>=', now()->subDay())
                ->count();

            if ($visitorVisitCount >= self::VISITOR_VISIT_LIMIT) {
                $flags[] = self::REASON_REPEATED_VISITOR;
            }
        }

        if ($ipHash && $userAgentHash) {
            $deviceVisitCount = AffiliateReferralVisit::query()
                ->where('affiliate_account_id', $referralCode->affiliate_account_id)
                ->where('ip_hash', $ipHash)
                ->where('user_agent_hash', $userAgentHash)
                ->where('visited_at', '>=', now()->subDay())
                ->count();

            if ($deviceVisitCount >= self::DEVICE_VISIT_LIMIT) {
                $flags[] = self::REASON_REPEATED_IP_USER_AGENT;
            }
        }

        $flags = array_values(array_unique($flags));

        return [
            'is_suspicious' => $flags !== [],
            'reason' => $flags[0] ?? null,
            'flags' => $flags,
        ];
    }

    public function assessSignup(
        User $user,
        AffiliateReferralCode $referralCode,
        AffiliateReferralVisit $visit,
        ?Request $request = null,
    ): array {
        $flags = [];
        $block = false;
        $reason = null;
        $signupIpHash = $this->hashValue($request?->ip());
        $signupUserAgentHash = $this->hashValue($request?->userAgent());

        if ((int) $referralCode->affiliateAccount->user_id === (int) $user->id) {
            $block = true;
            $reason = self::REASON_SELF_REFERRAL;
            $flags[] = self::REASON_SELF_REFERRAL;
        }

        if (! $block && AffiliateReferral::query()->where('referred_user_id', $user->id)->exists()) {
            $block = true;
            $reason = self::REASON_DUPLICATE_ATTRIBUTION;
            $flags[] = self::REASON_DUPLICATE_ATTRIBUTION;
        }

        if ($visit->is_suspicious) {
            $flags[] = self::REASON_SUSPICIOUS_VISIT;

            if ($visit->suspicious_reason) {
                $flags[] = $visit->suspicious_reason;
            }
        }

        $ipMatchesVisit = $this->hashesMatch($visit->ip_hash, $signupIpHash);
        $userAgentMatchesVisit = $this->hashesMatch($visit->user_agent_hash, $signupUserAgentHash);

        if ($visit->ip_hash && $signupIpHash && ! $ipMatchesVisit) {
            $flags[] = 'ip_hash_mismatch';
        }

        if ($visit->user_agent_hash && $signupUserAgentHash && ! $userAgentMatchesVisit) {
            $flags[] = 'user_agent_hash_mismatch';
        }

        if ($this->hasRecentSignupFromDevice($referralCode, $signupIpHash, $signupUserAgentHash)) {
            $flags[] = self::REASON_SHARED_DEVICE_SIGNUPS;
        }

        $flags = array_values(array_unique($flags));
        $isSuspicious = $block || $flags !== [];

        return [
            'block' => $block,
            'reason' => $reason ?: ($isSuspicious ? $this->signupReason($flags) : null),
            'is_suspicious' => $isSuspicious,
            'flags' => $flags,
            'signup_ip_hash' => $signupIpHash,
            'signup_user_agent_hash' => $signupUserAgentHash,
            'ip_hash_matches_visit' => $ipMatchesVisit,
            'user_agent_hash_matches_visit' => $userAgentMatchesVisit,
        ];
    }

    public function markDuplicateAttributionAttempt(AffiliateReferral $referral, array $metadata = []): AffiliateReferral
    {
        $flags = array_values(array_unique([
            ...(array) ($referral->fraud_flags ?? []),
            self::REASON_DUPLICATE_ATTRIBUTION,
        ]));
        $existingMetadata = $referral->metadata ?? [];

        $referral->forceFill([
            'is_suspicious' => true,
            'fraud_reason' => self::REASON_DUPLICATE_ATTRIBUTION,
            'fraud_flags' => $flags,
            'fraud_checked_at' => now(),
            'metadata' => [
                ...$existingMetadata,
                'duplicate_attribution_attempts' => ((int) ($existingMetadata['duplicate_attribution_attempts'] ?? 0)) + 1,
                'latest_duplicate_attribution_attempt' => [
                    ...$metadata,
                    'occurred_at' => now()->toISOString(),
                ],
            ],
        ])->save();

        return $referral;
    }

    public function hashValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return hash('sha256', $value);
    }

    private function hasRecentSignupFromDevice(
        AffiliateReferralCode $referralCode,
        ?string $signupIpHash,
        ?string $signupUserAgentHash,
    ): bool {
        if (! $signupIpHash || ! $signupUserAgentHash) {
            return false;
        }

        return AffiliateReferral::query()
            ->where('affiliate_account_id', $referralCode->affiliate_account_id)
            ->where('attributed_at', '>=', now()->subDay())
            ->where('metadata->signup_ip_hash', $signupIpHash)
            ->where('metadata->signup_user_agent_hash', $signupUserAgentHash)
            ->exists();
    }

    private function hashesMatch(?string $first, ?string $second): ?bool
    {
        if (! $first || ! $second) {
            return null;
        }

        return hash_equals($first, $second);
    }

    private function signupReason(array $flags): ?string
    {
        if (
            in_array('ip_hash_mismatch', $flags, true)
            || in_array('user_agent_hash_mismatch', $flags, true)
        ) {
            return self::REASON_SIGNUP_DEVICE_MISMATCH;
        }

        return $flags[0] ?? null;
    }
}
