<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AffiliateAccount;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReward;
use App\Models\AffiliateRewardAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class AffiliatePayoutService
{
    public const DEFAULT_CURRENCY = 'USD';

    public function __construct(private readonly AffiliateProgramSettings $settings)
    {
    }

    public function payableRewardsQuery(AffiliateAccount $account, string $currency = self::DEFAULT_CURRENCY)
    {
        return $account->rewards()
            ->where('status', AffiliateReward::STATUS_APPROVED)
            ->where('currency', strtoupper($currency))
            ->where('amount_cents', '>', 0)
            ->whereNull('affiliate_payout_id');
    }

    public function payableBalance(?AffiliateAccount $account, string $currency = self::DEFAULT_CURRENCY): array
    {
        if (! $account) {
            return $this->balancePayload(0, 0, $currency);
        }

        $base = $this->payableRewardsQuery($account, $currency);
        $amountCents = (int) (clone $base)->sum('amount_cents');
        $rewardCount = (int) (clone $base)->count();

        return $this->balancePayload($amountCents, $rewardCount, $currency);
    }

    public function requestPayout(
        AffiliateAccount $account,
        User $user,
        string $paymentMethod,
        ?string $notes = null,
        string $currency = self::DEFAULT_CURRENCY,
    ): AffiliatePayout {
        if (! $this->settings->payoutsEnabled()) {
            throw ValidationException::withMessages([
                'payout' => 'Affiliate payouts are not enabled for the current program.',
            ]);
        }

        if ((int) $account->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'payout' => 'That affiliate account does not belong to this user.',
            ]);
        }

        $paymentMethod = trim($paymentMethod);

        if ($paymentMethod === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Choose a payout method.',
            ]);
        }

        return DB::transaction(function () use ($account, $user, $paymentMethod, $notes, $currency): AffiliatePayout {
            /** @var Collection<int, AffiliateReward> $rewards */
            $rewards = $this->payableRewardsQuery($account, $currency)
                ->lockForUpdate()
                ->get();

            if ($rewards->isEmpty()) {
                throw ValidationException::withMessages([
                    'payout' => 'There is no approved payable balance available.',
                ]);
            }

            $amountCents = (int) $rewards->sum('amount_cents');

            $payout = AffiliatePayout::query()->create([
                'affiliate_account_id' => $account->id,
                'requested_by_user_id' => $user->id,
                'status' => AffiliatePayout::STATUS_REQUESTED,
                'amount_cents' => $amountCents,
                'currency' => strtoupper($currency),
                'reward_count' => $rewards->count(),
                'payment_method' => $paymentMethod,
                'requested_at' => now(),
                'notes' => $notes,
                'metadata' => [
                    'reward_ids' => $rewards->pluck('id')->values()->all(),
                    'requested_amount_label' => $this->money($amountCents, $currency),
                ],
            ]);

            $rewards->each(function (AffiliateReward $reward) use ($payout): void {
                $reward->forceFill([
                    'affiliate_payout_id' => $payout->id,
                    'metadata' => [
                        ...($reward->metadata ?? []),
                        'payout_requested_at' => now()->toISOString(),
                        'payout_id' => $payout->id,
                    ],
                ])->save();

                $this->auditReward($reward, 'payout_requested', $reward->status, $reward->status, $payout->requestedByUser, [
                    'affiliate_payout_id' => $payout->id,
                    'amount_cents' => $reward->amount_cents,
                    'currency' => $reward->currency,
                ]);
            });

            return $payout->fresh(['affiliateAccount.user', 'rewards']) ?? $payout;
        });
    }

    public function setStatus(
        AffiliatePayout $payout,
        string $status,
        ?Admin $admin = null,
        ?string $reference = null,
        ?string $reason = null,
        ?string $notes = null,
    ): AffiliatePayout {
        return match ($status) {
            AffiliatePayout::STATUS_APPROVED => $this->approve($payout, $admin, $notes),
            AffiliatePayout::STATUS_PROCESSING => $this->markProcessing($payout, $admin, $reference, $notes),
            AffiliatePayout::STATUS_PAID => $this->markPaid($payout, $admin, $reference, $notes),
            AffiliatePayout::STATUS_REJECTED => $this->reject($payout, $admin, $reason ?: 'Rejected by admin', $notes),
            AffiliatePayout::STATUS_CANCELLED => $this->cancel($payout, $admin, $reason ?: 'Cancelled by admin', $notes),
            default => $payout,
        };
    }

    public function approve(AffiliatePayout $payout, ?Admin $admin = null, ?string $notes = null): AffiliatePayout
    {
        return $this->transition($payout, AffiliatePayout::STATUS_APPROVED, 'approved_at', $admin, null, null, $notes);
    }

    public function markProcessing(
        AffiliatePayout $payout,
        ?Admin $admin = null,
        ?string $reference = null,
        ?string $notes = null,
    ): AffiliatePayout {
        return $this->transition($payout, AffiliatePayout::STATUS_PROCESSING, 'processing_at', $admin, $reference, null, $notes);
    }

    public function markPaid(
        AffiliatePayout $payout,
        ?Admin $admin = null,
        ?string $reference = null,
        ?string $notes = null,
    ): AffiliatePayout {
        return DB::transaction(function () use ($payout, $admin, $reference, $notes): AffiliatePayout {
            $payout = $this->transition($payout, AffiliatePayout::STATUS_PAID, 'paid_at', $admin, $reference, null, $notes);
            $payout->loadMissing('rewards');

            $payout->rewards->each(function (AffiliateReward $reward) use ($payout, $admin, $reference): void {
                $fromStatus = $reward->status;

                $reward->forceFill([
                    'status' => AffiliateReward::STATUS_PAID,
                    'paid_at' => $reward->paid_at ?? now(),
                    'issued_reference' => $reference ?: $reward->issued_reference,
                    'metadata' => [
                        ...($reward->metadata ?? []),
                        'payout_paid_at' => now()->toISOString(),
                        'payout_reference' => $reference,
                    ],
                ])->save();

                if ($fromStatus !== AffiliateReward::STATUS_PAID) {
                    $this->auditReward($reward, AffiliateRewardAudit::ACTION_PAID, $fromStatus, AffiliateReward::STATUS_PAID, $admin, [
                        'affiliate_payout_id' => $payout->id,
                        'payout_reference' => $reference,
                    ]);
                }
            });

            return $payout->fresh(['affiliateAccount.user', 'rewards']) ?? $payout;
        });
    }

    public function reject(
        AffiliatePayout $payout,
        ?Admin $admin = null,
        string $reason = 'Rejected by admin',
        ?string $notes = null,
    ): AffiliatePayout {
        return $this->releaseRewards(
            $this->transition($payout, AffiliatePayout::STATUS_REJECTED, 'rejected_at', $admin, null, $reason, $notes),
            $admin,
            'payout_rejected',
            $reason,
        );
    }

    public function cancel(
        AffiliatePayout $payout,
        ?Admin $admin = null,
        string $reason = 'Cancelled by admin',
        ?string $notes = null,
    ): AffiliatePayout {
        return $this->releaseRewards(
            $this->transition($payout, AffiliatePayout::STATUS_CANCELLED, 'cancelled_at', $admin, null, $reason, $notes),
            $admin,
            'payout_cancelled',
            $reason,
        );
    }

    public function payoutPayload(AffiliatePayout $payout): array
    {
        return [
            'id' => $payout->id,
            'status' => $payout->status,
            'amount_cents' => $payout->amount_cents,
            'amount_label' => $this->money($payout->amount_cents, $payout->currency),
            'currency' => $payout->currency,
            'reward_count' => $payout->reward_count,
            'payment_method' => $payout->payment_method,
            'payout_reference' => $payout->payout_reference,
            'requested_at' => $payout->requested_at?->toISOString(),
            'approved_at' => $payout->approved_at?->toISOString(),
            'processing_at' => $payout->processing_at?->toISOString(),
            'paid_at' => $payout->paid_at?->toISOString(),
            'rejected_at' => $payout->rejected_at?->toISOString(),
            'cancelled_at' => $payout->cancelled_at?->toISOString(),
            'rejection_reason' => $payout->rejection_reason,
            'notes' => $payout->notes,
        ];
    }

    private function transition(
        AffiliatePayout $payout,
        string $status,
        string $timestampColumn,
        ?Admin $admin = null,
        ?string $reference = null,
        ?string $reason = null,
        ?string $notes = null,
    ): AffiliatePayout {
        $metadata = $payout->metadata ?? [];
        $history = $metadata['status_history'] ?? [];

        $history[] = [
            'from_status' => $payout->status,
            'to_status' => $status,
            'admin_id' => $admin?->getKey(),
            'reference' => $reference,
            'reason' => $reason,
            'occurred_at' => now()->toISOString(),
        ];

        $payout->forceFill([
            'status' => $status,
            'processed_by_admin_id' => $admin?->id ?? $payout->processed_by_admin_id,
            'payout_reference' => $reference ?: $payout->payout_reference,
            'rejection_reason' => in_array($status, [
                AffiliatePayout::STATUS_REJECTED,
                AffiliatePayout::STATUS_CANCELLED,
            ], true) ? $reason : $payout->rejection_reason,
            'notes' => $notes ?: $payout->notes,
            $timestampColumn => $payout->{$timestampColumn} ?? now(),
            'metadata' => [
                ...$metadata,
                'status_history' => $history,
            ],
        ])->save();

        return $payout->fresh(['affiliateAccount.user', 'requestedByUser', 'processedByAdmin', 'rewards']) ?? $payout;
    }

    private function releaseRewards(
        AffiliatePayout $payout,
        ?Admin $admin,
        string $action,
        string $reason,
    ): AffiliatePayout {
        return DB::transaction(function () use ($payout, $admin, $action, $reason): AffiliatePayout {
            $payout->loadMissing('rewards');

            $payout->rewards->each(function (AffiliateReward $reward) use ($payout, $admin, $action, $reason): void {
                $reward->forceFill([
                    'affiliate_payout_id' => null,
                    'metadata' => [
                        ...($reward->metadata ?? []),
                        $action.'_at' => now()->toISOString(),
                        $action.'_reason' => $reason,
                    ],
                ])->save();

                $this->auditReward($reward, $action, $reward->status, $reward->status, $admin, [
                    'affiliate_payout_id' => $payout->id,
                    'reason' => $reason,
                ]);
            });

            return $payout->fresh(['affiliateAccount.user', 'rewards']) ?? $payout;
        });
    }

    private function auditReward(
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

    private function balancePayload(int $amountCents, int $rewardCount, string $currency): array
    {
        return [
            'amount_cents' => $amountCents,
            'amount_label' => $this->money($amountCents, $currency),
            'currency' => strtoupper($currency),
            'reward_count' => $rewardCount,
            'can_request_payout' => $amountCents > 0 && $rewardCount > 0,
        ];
    }

    private function money(int $amountCents, string $currency): string
    {
        return Number::currency($amountCents / 100, strtoupper($currency));
    }
}
