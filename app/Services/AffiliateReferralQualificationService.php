<?php

namespace App\Services;

use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AffiliateReferralQualificationService
{
    public function __construct(
        private readonly AffiliateRewardService $rewards,
        private readonly AffiliateNotificationService $notifications,
    ) {}

    public function qualifySubscription(
        User $user,
        string $provider,
        string $transactionId,
        string $source,
        ?string $planKey = null,
        ?string $status = null,
        array $metadata = [],
    ): ?AffiliateReferral {
        if ($transactionId === '') {
            return null;
        }

        $referral = AffiliateReferral::query()
            ->where('referred_user_id', $user->id)
            ->whereIn('status', [
                AffiliateReferral::STATUS_PENDING,
                AffiliateReferral::STATUS_QUALIFIED,
            ])
            ->first();

        if (! $referral) {
            return null;
        }

        return $this->qualifyReferral(
            referral: $referral,
            provider: $provider,
            transactionId: $transactionId,
            source: $source,
            planKey: $planKey,
            status: $status,
            metadata: $metadata,
        );
    }

    public function qualifyReferral(
        AffiliateReferral $referral,
        string $provider,
        string $transactionId,
        string $source,
        ?string $planKey = null,
        ?string $status = null,
        array $metadata = [],
    ): AffiliateReferral {
        return DB::transaction(function () use (
            $referral,
            $provider,
            $transactionId,
            $source,
            $planKey,
            $status,
            $metadata,
        ): AffiliateReferral {
            if (
                $referral->is_suspicious
                && $referral->status !== AffiliateReferral::STATUS_QUALIFIED
                && $source !== 'admin_manual'
            ) {
                $flags = array_values(array_unique([
                    ...(array) ($referral->fraud_flags ?? []),
                    'qualification_blocked',
                ]));

                $referral->forceFill([
                    'status' => AffiliateReferral::STATUS_REJECTED,
                    'qualified_at' => null,
                    'qualified_transaction_type' => null,
                    'qualified_transaction_id' => null,
                    'rejected_at' => now(),
                    'rejection_reason' => $referral->fraud_reason ?: 'suspicious_affiliate_activity',
                    'is_suspicious' => true,
                    'fraud_reason' => $referral->fraud_reason ?: 'suspicious_affiliate_activity',
                    'fraud_flags' => $flags,
                    'fraud_checked_at' => now(),
                    'metadata' => [
                        ...($referral->metadata ?? []),
                        'blocked_qualification' => [
                            'provider' => $provider,
                            'transaction_id' => $transactionId,
                            'source' => $source,
                            'plan' => $planKey,
                            'status' => $status,
                            'occurred_at' => now()->toISOString(),
                        ],
                    ],
                ])->save();

                return $referral->fresh(['events', 'referralCode', 'referralVisit', 'rewards']) ?? $referral;
            }

            $referral->forceFill([
                'status' => AffiliateReferral::STATUS_QUALIFIED,
                'qualified_at' => $referral->qualified_at ?? now(),
                'qualified_transaction_type' => $provider,
                'qualified_transaction_id' => $transactionId,
                'rejected_at' => null,
                'rejection_reason' => null,
                'is_suspicious' => $source === 'admin_manual' ? false : $referral->is_suspicious,
            ])->save();

            AffiliateReferralEvent::query()->firstOrCreate(
                [
                    'event_hash' => $this->eventHash($referral, $provider, $transactionId),
                ],
                [
                    'affiliate_referral_id' => $referral->id,
                    'event_type' => AffiliateReferralEvent::TYPE_SUBSCRIPTION_QUALIFIED,
                    'event_source' => $source,
                    'target_type' => 'user_subscription',
                    'target_id' => $referral->referred_user_id,
                    'transaction_type' => $provider,
                    'transaction_id' => $transactionId,
                    'occurred_at' => now(),
                    'metadata' => [
                        ...$metadata,
                        'provider' => $provider,
                        'plan' => $planKey,
                        'status' => $status,
                    ],
                ],
            );

            $freshReferral = $referral->fresh(['events', 'referralCode', 'referralVisit']) ?? $referral;
            $this->rewards->createMembershipCreditForQualifiedReferral($freshReferral, [
                'metadata' => [
                    'qualification_source' => $source,
                    'provider' => $provider,
                    'plan' => $planKey,
                    'status' => $status,
                ],
            ]);
            $this->notifications->referralQualified($freshReferral);

            return $freshReferral->fresh(['events', 'referralCode', 'referralVisit', 'rewards']) ?? $freshReferral;
        });
    }

    public function publicQualification(?AffiliateReferral $referral): ?array
    {
        if (! $referral || $referral->status !== AffiliateReferral::STATUS_QUALIFIED) {
            return null;
        }

        return [
            'referral_id' => $referral->id,
            'status' => $referral->status,
            'qualified_at' => $referral->qualified_at?->toISOString(),
            'transaction_type' => $referral->qualified_transaction_type,
            'transaction_id' => $referral->qualified_transaction_id,
            'referral_code' => $referral->referralCode?->code,
        ];
    }

    private function eventHash(AffiliateReferral $referral, string $provider, string $transactionId): string
    {
        return hash('sha256', implode('|', [
            $referral->id,
            AffiliateReferralEvent::TYPE_SUBSCRIPTION_QUALIFIED,
            $provider,
            $transactionId,
        ]));
    }
}
