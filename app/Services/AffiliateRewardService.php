<?php

namespace App\Services;

use App\Models\AffiliateReferral;
use App\Models\AffiliateReward;
use App\Models\AffiliateRewardAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AffiliateRewardService
{
    public function __construct(
        private readonly AffiliateNotificationService $notifications,
        private readonly AffiliateProgramSettings $settings,
    ) {}

    public function createForQualifiedReferral(
        AffiliateReferral $referral,
        array $attributes = [],
    ): ?AffiliateReward {
        if ($referral->status !== AffiliateReferral::STATUS_QUALIFIED) {
            return null;
        }

        return DB::transaction(function () use ($referral, $attributes): AffiliateReward {
            $reward = AffiliateReward::query()->firstOrCreate(
                [
                    'affiliate_referral_id' => $referral->id,
                    'reward_type' => $attributes['reward_type'] ?? AffiliateReward::TYPE_FUTURE_INCENTIVE,
                    'source' => $attributes['source'] ?? 'subscription_qualification',
                ],
                [
                    'affiliate_account_id' => $referral->affiliate_account_id,
                    'status' => AffiliateReward::STATUS_PENDING,
                    'amount_cents' => $attributes['amount_cents'] ?? null,
                    'currency' => $attributes['currency'] ?? 'USD',
                    'points' => $attributes['points'] ?? null,
                    'quantity' => $attributes['quantity'] ?? 1,
                    'available_at' => $attributes['available_at'] ?? now(),
                    'metadata' => [
                        'qualification_transaction_type' => $referral->qualified_transaction_type,
                        'qualification_transaction_id' => $referral->qualified_transaction_id,
                        ...($attributes['metadata'] ?? []),
                    ],
                ],
            );

            if ($reward->wasRecentlyCreated) {
                $this->audit(
                    reward: $reward,
                    action: AffiliateRewardAudit::ACTION_CREATED,
                    fromStatus: null,
                    toStatus: $reward->status,
                    metadata: [
                        'source' => $reward->source,
                        'reward_type' => $reward->reward_type,
                    ],
                );
            }

            return $reward->fresh(['audits', 'referral']) ?? $reward;
        });
    }

    public function createMembershipCreditForQualifiedReferral(
        AffiliateReferral $referral,
        array $attributes = [],
    ): ?AffiliateReward {
        if ($referral->status !== AffiliateReferral::STATUS_QUALIFIED) {
            return null;
        }

        $referral->loadMissing('affiliateAccount.user');

        if (! $referral->affiliateAccount?->user) {
            return null;
        }

        $creditDays = (int) ($attributes['membership_credit_days'] ?? $this->settings->membershipCreditDays());
        $expiresAfterMonths = (int) ($attributes['expires_after_months'] ?? $this->settings->membershipCreditExpirationMonths());
        $expiresAt = $attributes['expires_at'] ?? now()->addMonthsNoOverflow($expiresAfterMonths);

        return DB::transaction(function () use ($referral, $attributes, $creditDays, $expiresAfterMonths, $expiresAt): AffiliateReward {
            $reward = AffiliateReward::query()->firstOrCreate(
                [
                    'affiliate_referral_id' => $referral->id,
                    'reward_type' => AffiliateReward::TYPE_MEMBERSHIP_CREDIT,
                    'source' => $attributes['source'] ?? 'subscription_qualification',
                ],
                [
                    'affiliate_account_id' => $referral->affiliate_account_id,
                    'status' => AffiliateReward::STATUS_PENDING,
                    'amount_cents' => null,
                    'currency' => $attributes['currency'] ?? 'USD',
                    'points' => null,
                    'quantity' => $attributes['quantity'] ?? 1,
                    'membership_credit_days' => $creditDays,
                    'available_at' => $attributes['available_at'] ?? now(),
                    'expires_at' => $expiresAt,
                    'metadata' => [
                        'membership_tier' => $attributes['membership_tier'] ?? $this->settings->membershipCreditTier(),
                        'membership_credit_days' => $creditDays,
                        'expires_after_months' => $expiresAfterMonths,
                        'reward_plan' => $this->settings->rewardPlan(),
                        'qualification_event' => $this->settings->qualificationEvent(),
                        'qualification_transaction_type' => $referral->qualified_transaction_type,
                        'qualification_transaction_id' => $referral->qualified_transaction_id,
                        ...($attributes['metadata'] ?? []),
                    ],
                ],
            );

            if ($reward->wasRecentlyCreated) {
                $this->audit(
                    reward: $reward,
                    action: AffiliateRewardAudit::ACTION_CREATED,
                    fromStatus: null,
                    toStatus: $reward->status,
                    metadata: [
                        'source' => $reward->source,
                        'reward_type' => $reward->reward_type,
                        'membership_credit_days' => $reward->membership_credit_days,
                        'expires_at' => $reward->expires_at?->toISOString(),
                    ],
                );
            }

            if ($reward->status === AffiliateReward::STATUS_PENDING) {
                $reward = $this->issue(
                    reward: $reward,
                    issuedReference: 'membership-credit-'.$reward->id,
                    metadata: [
                        'source' => 'subscription_qualification',
                        'membership_credit_days' => $reward->membership_credit_days,
                        'expires_at' => $reward->expires_at?->toISOString(),
                    ],
                );
            }

            $this->notifications->membershipCreditIssued($reward);

            return $reward->fresh(['audits', 'referral']) ?? $reward;
        });
    }

    public function approve(AffiliateReward $reward, ?Model $actor = null, array $metadata = []): AffiliateReward
    {
        return $this->transition(
            reward: $reward,
            toStatus: AffiliateReward::STATUS_APPROVED,
            action: AffiliateRewardAudit::ACTION_APPROVED,
            timestampColumn: 'approved_at',
            actor: $actor,
            metadata: $metadata,
        );
    }

    public function issue(
        AffiliateReward $reward,
        ?string $issuedReference = null,
        ?Model $actor = null,
        array $metadata = [],
    ): AffiliateReward {
        return $this->transition(
            reward: $reward,
            toStatus: AffiliateReward::STATUS_ISSUED,
            action: AffiliateRewardAudit::ACTION_ISSUED,
            timestampColumn: 'issued_at',
            actor: $actor,
            metadata: [
                ...$metadata,
                'issued_reference' => $issuedReference,
            ],
            updates: [
                'issued_reference' => $issuedReference,
            ],
        );
    }

    public function markPaid(AffiliateReward $reward, ?Model $actor = null, array $metadata = []): AffiliateReward
    {
        return $this->transition(
            reward: $reward,
            toStatus: AffiliateReward::STATUS_PAID,
            action: AffiliateRewardAudit::ACTION_PAID,
            timestampColumn: 'paid_at',
            actor: $actor,
            metadata: $metadata,
        );
    }

    public function cancel(AffiliateReward $reward, ?Model $actor = null, array $metadata = []): AffiliateReward
    {
        return $this->transition(
            reward: $reward,
            toStatus: AffiliateReward::STATUS_CANCELLED,
            action: AffiliateRewardAudit::ACTION_CANCELLED,
            timestampColumn: 'cancelled_at',
            actor: $actor,
            metadata: $metadata,
        );
    }

    public function void(AffiliateReward $reward, ?Model $actor = null, array $metadata = []): AffiliateReward
    {
        return $this->transition(
            reward: $reward,
            toStatus: AffiliateReward::STATUS_VOIDED,
            action: AffiliateRewardAudit::ACTION_VOIDED,
            timestampColumn: 'voided_at',
            actor: $actor,
            metadata: $metadata,
        );
    }

    public function setStatus(AffiliateReward $reward, string $status, ?Model $actor = null, array $metadata = []): AffiliateReward
    {
        return match ($status) {
            AffiliateReward::STATUS_APPROVED => $this->approve($reward, $actor, $metadata),
            AffiliateReward::STATUS_ISSUED => $this->issue($reward, $metadata['issued_reference'] ?? null, $actor, $metadata),
            AffiliateReward::STATUS_PAID => $this->markPaid($reward, $actor, $metadata),
            AffiliateReward::STATUS_REDEEMED => $this->markRedeemed($reward, $actor, $metadata),
            AffiliateReward::STATUS_EXPIRED => $this->expireMembershipCredit($reward, $actor, $metadata),
            AffiliateReward::STATUS_CANCELLED => $this->cancel($reward, $actor, $metadata),
            AffiliateReward::STATUS_VOIDED => $this->void($reward, $actor, $metadata),
            AffiliateReward::STATUS_PENDING => $this->markPending($reward, $actor, $metadata),
            default => $reward,
        };
    }

    public function redeemMembershipCredit(AffiliateReward $reward, User $user): AffiliateReward
    {
        $reward->refresh();

        if ($reward->reward_type !== AffiliateReward::TYPE_MEMBERSHIP_CREDIT) {
            throw ValidationException::withMessages([
                'reward' => 'That reward is not a membership credit.',
            ]);
        }

        $reward->loadMissing('affiliateAccount');

        if ($reward->affiliateAccount?->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'reward' => 'That membership credit does not belong to this account.',
            ]);
        }

        if ($reward->redeemed_at || $reward->status === AffiliateReward::STATUS_REDEEMED) {
            throw ValidationException::withMessages([
                'reward' => 'That membership credit has already been redeemed.',
            ]);
        }

        if ($reward->expires_at && $reward->expires_at->lte(now())) {
            $this->expireMembershipCredit($reward);

            throw ValidationException::withMessages([
                'reward' => 'That membership credit has expired.',
            ]);
        }

        if ($reward->status !== AffiliateReward::STATUS_ISSUED) {
            throw ValidationException::withMessages([
                'reward' => 'That membership credit is not available to redeem.',
            ]);
        }

        return DB::transaction(function () use ($reward, $user): AffiliateReward {
            $reward->refresh();

            $creditDays = $reward->membership_credit_days ?: $this->settings->membershipCreditDays();
            $startsAt = $user->billing_provider === 'internal'
                && $user->comped_subscription_expires_at
                && $user->comped_subscription_expires_at->isFuture()
                    ? $user->comped_subscription_expires_at->copy()
                    : now();
            $membershipEndsAt = $startsAt->copy()->addDays($creditDays);

            $user->forceFill([
                'media_storage_tier' => $this->settings->membershipCreditTier(),
                'paypal_subscription_status' => 'active',
                'billing_provider' => 'internal',
                'paypal_subscription_id' => null,
                'paypal_plan_id' => null,
                'paypal_subscription_approved_at' => now(),
                'comped_subscription_expires_at' => $membershipEndsAt,
                'comped_subscription_reason' => 'Affiliate membership credit reward #'.$reward->id,
                'comped_by_user_id' => null,
            ])->save();

            $fromStatus = $reward->status;
            $metadata = [
                ...($reward->metadata ?? []),
                'redeemed_user_id' => $user->id,
                'membership_started_at' => $startsAt->toISOString(),
                'membership_expires_at' => $membershipEndsAt->toISOString(),
            ];

            $reward->forceFill([
                'status' => AffiliateReward::STATUS_REDEEMED,
                'redeemed_at' => now(),
                'paid_at' => now(),
                'metadata' => $metadata,
            ])->save();

            $this->audit(
                reward: $reward,
                action: AffiliateRewardAudit::ACTION_REDEEMED,
                fromStatus: $fromStatus,
                toStatus: AffiliateReward::STATUS_REDEEMED,
                actor: $user,
                metadata: [
                    'membership_credit_days' => $creditDays,
                    'membership_started_at' => $startsAt->toISOString(),
                    'membership_expires_at' => $membershipEndsAt->toISOString(),
                ],
            );
            $this->notifications->membershipCreditRedeemed($reward, $user);

            return $reward->fresh(['audits', 'referral']) ?? $reward;
        });
    }

    public function notifyExpiringMembershipCredits(?int $days = null): int
    {
        $days ??= $this->settings->expiringSoonNotificationDays();

        return AffiliateReward::query()
            ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
            ->where('status', AffiliateReward::STATUS_ISSUED)
            ->whereNull('redeemed_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($days))
            ->get()
            ->reduce(function (int $count, AffiliateReward $reward): int {
                return $this->notifications->membershipCreditExpiringSoon($reward)
                    ? $count + 1
                    : $count;
            }, 0);
    }

    public function expireUnusedMembershipCredits(): int
    {
        return AffiliateReward::query()
            ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
            ->where('status', AffiliateReward::STATUS_ISSUED)
            ->whereNull('redeemed_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get()
            ->reduce(function (int $count, AffiliateReward $reward): int {
                $this->expireMembershipCredit($reward);

                return $count + 1;
            }, 0);
    }

    public function expireMembershipCredit(
        AffiliateReward $reward,
        ?Model $actor = null,
        array $metadata = [],
    ): AffiliateReward {
        return DB::transaction(function () use ($reward, $actor, $metadata): AffiliateReward {
            $reward->refresh();

            if ($reward->reward_type !== AffiliateReward::TYPE_MEMBERSHIP_CREDIT || $reward->redeemed_at) {
                return $reward;
            }

            if ($reward->status === AffiliateReward::STATUS_EXPIRED) {
                return $reward;
            }

            $fromStatus = $reward->status;

            $reward->forceFill([
                'status' => AffiliateReward::STATUS_EXPIRED,
                'voided_at' => now(),
            ])->save();

            $this->audit(
                reward: $reward,
                action: AffiliateRewardAudit::ACTION_EXPIRED,
                fromStatus: $fromStatus,
                toStatus: AffiliateReward::STATUS_EXPIRED,
                actor: $actor,
                metadata: [
                    ...$metadata,
                    'expires_at' => $reward->expires_at?->toISOString(),
                ],
            );
            $this->notifications->membershipCreditExpired($reward);

            return $reward->fresh(['audits', 'referral']) ?? $reward;
        });
    }

    private function markPending(AffiliateReward $reward, ?Model $actor = null, array $metadata = []): AffiliateReward
    {
        return DB::transaction(function () use ($reward, $actor, $metadata): AffiliateReward {
            $fromStatus = $reward->status;

            $reward->forceFill([
                'status' => AffiliateReward::STATUS_PENDING,
                'approved_at' => null,
                'issued_at' => null,
                'paid_at' => null,
                'redeemed_at' => null,
                'cancelled_at' => null,
                'voided_at' => null,
                'issued_reference' => null,
            ])->save();

            if ($fromStatus !== AffiliateReward::STATUS_PENDING) {
                $this->audit(
                    reward: $reward,
                    action: AffiliateRewardAudit::ACTION_STATUS_CHANGED,
                    fromStatus: $fromStatus,
                    toStatus: AffiliateReward::STATUS_PENDING,
                    actor: $actor,
                    metadata: $metadata,
                );
            }

            return $reward->fresh(['audits', 'referral']) ?? $reward;
        });
    }

    private function markRedeemed(AffiliateReward $reward, ?Model $actor = null, array $metadata = []): AffiliateReward
    {
        return $this->transition(
            reward: $reward,
            toStatus: AffiliateReward::STATUS_REDEEMED,
            action: AffiliateRewardAudit::ACTION_REDEEMED,
            timestampColumn: 'redeemed_at',
            actor: $actor,
            metadata: $metadata,
            updates: [
                'paid_at' => $reward->paid_at ?? now(),
            ],
        );
    }

    private function transition(
        AffiliateReward $reward,
        string $toStatus,
        string $action,
        string $timestampColumn,
        ?Model $actor = null,
        array $metadata = [],
        array $updates = [],
    ): AffiliateReward {
        return DB::transaction(function () use (
            $reward,
            $toStatus,
            $action,
            $timestampColumn,
            $actor,
            $metadata,
            $updates,
        ): AffiliateReward {
            $fromStatus = $reward->status;

            $reward->forceFill([
                ...$updates,
                'status' => $toStatus,
                $timestampColumn => $reward->{$timestampColumn} ?? now(),
            ])->save();

            if ($fromStatus !== $toStatus) {
                $this->audit(
                    reward: $reward,
                    action: $action,
                    fromStatus: $fromStatus,
                    toStatus: $toStatus,
                    actor: $actor,
                    metadata: $metadata,
                );
            }

            return $reward->fresh(['audits', 'referral']) ?? $reward;
        });
    }

    private function audit(
        AffiliateReward $reward,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?Model $actor = null,
        array $metadata = [],
    ): AffiliateRewardAudit {
        return AffiliateRewardAudit::query()->create([
            'affiliate_reward_id' => $reward->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->getKey(),
            'occurred_at' => now(),
            'metadata' => $metadata,
        ]);
    }
}
