<?php

namespace App\Services;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AffiliateReferralAttributionService
{
    public function __construct(
        private readonly AffiliateNotificationService $notifications,
        private readonly AffiliateFraudProtectionService $fraud,
    ) {}

    public function attributeSignup(User $user, ?array $context, ?Request $request = null): ?AffiliateReferral
    {
        if (! $context) {
            return null;
        }

        $referralCode = $this->referralCode($context);
        $visit = $this->referralVisit($context);

        if (! $referralCode || ! $visit) {
            return null;
        }

        $existingReferral = AffiliateReferral::query()
            ->where('referred_user_id', $user->id)
            ->first();

        if ($existingReferral) {
            return $this->fraud->markDuplicateAttributionAttempt($existingReferral, [
                'referral_code_id' => $referralCode->id,
                'referral_visit_id' => $visit->id,
            ])->fresh(['affiliateAccount.user', 'referredUser', 'referralCode', 'referralVisit']) ?? $existingReferral;
        }

        $fraudAssessment = $this->fraud->assessSignup($user, $referralCode, $visit, $request);

        if ($fraudAssessment['block']) {
            return $this->rejectSignup($user, $referralCode, $visit, $fraudAssessment['reason'], $fraudAssessment);
        }

        return DB::transaction(function () use ($user, $context, $referralCode, $visit, $fraudAssessment): AffiliateReferral {
            $referral = AffiliateReferral::query()->firstOrCreate(
                ['referred_user_id' => $user->id],
                [
                    'affiliate_account_id' => $referralCode->affiliate_account_id,
                    'affiliate_campaign_id' => $visit->affiliate_campaign_id ?: $referralCode->affiliate_campaign_id,
                    'affiliate_referral_code_id' => $referralCode->id,
                    'affiliate_referral_visit_id' => $visit->id,
                    'status' => AffiliateReferral::STATUS_PENDING,
                    'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
                    'attributed_at' => now(),
                    'is_suspicious' => $fraudAssessment['is_suspicious'],
                    'fraud_reason' => $fraudAssessment['reason'],
                    'fraud_flags' => $fraudAssessment['flags'],
                    'fraud_checked_at' => now(),
                    'metadata' => [
                        'referral_code' => $referralCode->code,
                        'captured_at' => Arr::get($context, 'captured_at'),
                        'expires_at' => Arr::get($context, 'expires_at'),
                        'signup_ip_hash' => $fraudAssessment['signup_ip_hash'],
                        'signup_user_agent_hash' => $fraudAssessment['signup_user_agent_hash'],
                        'ip_hash_matches_visit' => $fraudAssessment['ip_hash_matches_visit'],
                        'user_agent_hash_matches_visit' => $fraudAssessment['user_agent_hash_matches_visit'],
                    ],
                ],
            );

            if ($referral->wasRecentlyCreated && ! $visit->converted_user_id) {
                $visit->forceFill([
                    'converted_user_id' => $user->id,
                    'converted_at' => now(),
                ])->save();
            }

            $freshReferral = $referral->fresh(['affiliateAccount.user', 'referredUser', 'referralCode', 'referralVisit']) ?? $referral;

            if ($referral->wasRecentlyCreated) {
                $this->notifications->referralSignedUp($freshReferral);
            }

            return $freshReferral;
        });
    }

    public function publicAttribution(?AffiliateReferral $referral): ?array
    {
        if (! $referral) {
            return null;
        }

        return [
            'id' => $referral->id,
            'status' => $referral->status,
            'attribution_type' => $referral->attribution_type,
            'affiliate_account_id' => $referral->affiliate_account_id,
            'referral_code' => $referral->referralCode?->code,
            'referral_visit_id' => $referral->affiliate_referral_visit_id,
            'is_suspicious' => $referral->is_suspicious,
            'fraud_reason' => $referral->fraud_reason,
            'fraud_flags' => $referral->fraud_flags ?? [],
            'rejection_reason' => $referral->rejection_reason,
            'attributed_at' => $referral->attributed_at?->toISOString(),
        ];
    }

    private function rejectSignup(
        User $user,
        AffiliateReferralCode $referralCode,
        AffiliateReferralVisit $visit,
        string $reason,
        array $fraudAssessment = [],
    ): AffiliateReferral {
        $referral = AffiliateReferral::query()->firstOrCreate(
            ['referred_user_id' => $user->id],
            [
                'affiliate_account_id' => $referralCode->affiliate_account_id,
                'affiliate_campaign_id' => $visit->affiliate_campaign_id ?: $referralCode->affiliate_campaign_id,
                'affiliate_referral_code_id' => $referralCode->id,
                'affiliate_referral_visit_id' => $visit->id,
                'status' => AffiliateReferral::STATUS_REJECTED,
                'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
                'attributed_at' => now(),
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'is_suspicious' => true,
                'fraud_reason' => $reason,
                'fraud_flags' => $fraudAssessment['flags'] ?? [$reason],
                'fraud_checked_at' => now(),
                'metadata' => [
                    'referral_code' => $referralCode->code,
                    'signup_ip_hash' => $fraudAssessment['signup_ip_hash'] ?? null,
                    'signup_user_agent_hash' => $fraudAssessment['signup_user_agent_hash'] ?? null,
                    'ip_hash_matches_visit' => $fraudAssessment['ip_hash_matches_visit'] ?? null,
                    'user_agent_hash_matches_visit' => $fraudAssessment['user_agent_hash_matches_visit'] ?? null,
                ],
            ],
        );

        return $referral->fresh(['affiliateAccount', 'referralCode', 'referralVisit']) ?? $referral;
    }

    private function referralCode(array $context): ?AffiliateReferralCode
    {
        $codeId = Arr::get($context, 'affiliate_referral_code_id');

        if (! $codeId) {
            return null;
        }

        return AffiliateReferralCode::query()
            ->with('affiliateAccount')
            ->whereKey($codeId)
            ->where('status', AffiliateReferralCode::STATUS_ACTIVE)
            ->whereHas('affiliateAccount', function ($query): void {
                $query->where('status', AffiliateAccount::STATUS_ACTIVE);
            })
            ->first();
    }

    private function referralVisit(array $context): ?AffiliateReferralVisit
    {
        $visitId = Arr::get($context, 'referral_visit_id');

        if (! $visitId) {
            return null;
        }

        return AffiliateReferralVisit::query()
            ->whereKey($visitId)
            ->whereNull('converted_user_id')
            ->first();
    }
}
