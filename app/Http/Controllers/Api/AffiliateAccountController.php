<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReward;
use App\Models\User;
use App\Services\AffiliateAccountService;
use App\Services\AffiliatePayoutService;
use App\Services\AffiliateRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class AffiliateAccountController extends Controller
{
    public function show(AffiliateAccountService $affiliates): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $account = $user->affiliateAccount()->first();

        if ($account) {
            $affiliates->ensureDefaultReferralCode($account);
            $account->load('defaultReferralCode');
        }

        return response()->json([
            'affiliate_account' => $this->accountPayload($account),
        ]);
    }

    public function store(Request $request, AffiliateAccountService $affiliates): JsonResponse
    {
        $attributes = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        /** @var User $user */
        $user = Auth::guard('web')->user();

        $registration = $affiliates->register($user, $attributes);

        return response()->json([
            'affiliate_account' => $this->accountPayload($registration['account']),
            'outputs' => [
                'affiliate_account_created' => $registration['created'],
                'affiliate_status_assigned' => $registration['account']->status,
                'affiliate_profile_established' => true,
            ],
        ], $registration['created'] ? 201 : 200);
    }

    public function summary(AffiliateAccountService $affiliates, AffiliatePayoutService $payouts): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $account = $this->accountFor($user, $affiliates);

        return response()->json([
            'affiliate_account' => $this->accountPayload($account),
            'referral_statistics' => $this->referralStatistics($account),
            'qualification_statistics' => $this->qualificationStatistics($account),
            'reward_statistics' => $this->rewardStatistics($account),
            'payout_balance' => $payouts->payableBalance($account),
            'payout_statistics' => $this->payoutStatistics($account),
            'payout_history' => $this->payoutHistory($account, $payouts),
            'referral_activity' => $this->referralActivity($account),
            'reward_activity' => $this->rewardActivity($account),
        ]);
    }

    public function referrals(AffiliateAccountService $affiliates): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $account = $this->accountFor($user, $affiliates);

        return response()->json([
            'referrals' => $account ? $account->referrals()
                ->with(['referredUser:id,name,email', 'referralCode:id,code', 'referralVisit:id,visited_at,landing_url,is_suspicious,suspicious_reason'])
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (AffiliateReferral $referral): array => $this->referralPayload($referral))
                ->values()
                ->all() : [],
            'statistics' => $this->qualificationStatistics($account),
        ]);
    }

    public function rewards(AffiliateAccountService $affiliates): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $account = $this->accountFor($user, $affiliates);

        return response()->json([
            'rewards' => $account ? $account->rewards()
                ->with(['referral.referredUser:id,name,email', 'audits' => fn ($query) => $query->latest('id')->limit(5)])
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (AffiliateReward $reward): array => $this->rewardPayload($reward))
                ->values()
                ->all() : [],
            'statistics' => $this->rewardStatistics($account),
            'activity' => $this->rewardActivity($account),
        ]);
    }

    public function payouts(AffiliateAccountService $affiliates, AffiliatePayoutService $payouts): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $account = $this->accountFor($user, $affiliates);

        return response()->json([
            'balance' => $payouts->payableBalance($account),
            'statistics' => $this->payoutStatistics($account),
            'payouts' => $this->payoutHistory($account, $payouts, 25),
        ]);
    }

    public function requestPayout(
        Request $request,
        AffiliateAccountService $affiliates,
        AffiliatePayoutService $payouts,
    ): JsonResponse {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $account = $this->accountFor($user, $affiliates);

        abort_unless($account, 404);

        $payout = $payouts->requestPayout(
            account: $account,
            user: $user,
            paymentMethod: $validated['payment_method'],
            notes: $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Payout requested.',
            'balance' => $payouts->payableBalance($account->fresh()),
            'payout' => $payouts->payoutPayload($payout),
        ], 201);
    }

    public function redeemReward(
        AffiliateReward $reward,
        AffiliateAccountService $affiliates,
        AffiliateRewardService $rewards,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $account = $this->accountFor($user, $affiliates);

        abort_unless($account && $reward->affiliate_account_id === $account->id, 404);

        $redeemedReward = $rewards->redeemMembershipCredit($reward, $user);

        return response()->json([
            'message' => 'Membership credit redeemed.',
            'reward' => $this->rewardPayload($redeemedReward->load(['referral.referredUser:id,name,email', 'audits'])),
            'subscription' => [
                'plan' => $user->fresh()->media_storage_tier,
                'billing_provider' => $user->fresh()->billing_provider,
                'expires_at' => $user->fresh()->comped_subscription_expires_at?->toISOString(),
                'reason' => $user->fresh()->comped_subscription_reason,
            ],
        ]);
    }

    private function accountPayload(?AffiliateAccount $account): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'id' => $account->id,
            'status' => $account->status,
            'display_name' => $account->display_name,
            'contact_email' => $account->contact_email,
            'referral_code' => $account->defaultReferralCode?->code,
            'referral_link' => $this->referralLink($account),
            'joined_at' => $account->joined_at?->toISOString(),
            'approved_at' => $account->approved_at?->toISOString(),
            'paused_at' => $account->paused_at?->toISOString(),
            'banned_at' => $account->banned_at?->toISOString(),
        ];
    }

    private function accountFor(User $user, AffiliateAccountService $affiliates): ?AffiliateAccount
    {
        $account = $user->affiliateAccount()->first();

        if (! $account) {
            return null;
        }

        $affiliates->ensureDefaultReferralCode($account);

        return $account->fresh('defaultReferralCode') ?? $account;
    }

    private function referralStatistics(?AffiliateAccount $account): array
    {
        $visits = $account?->referralVisits()->count() ?? 0;
        $signups = $account?->referrals()->count() ?? 0;

        return [
            'visits' => $visits,
            'signups' => $signups,
            'conversion_rate' => $visits > 0 ? round(($signups / $visits) * 100, 2) : 0.0,
        ];
    }

    private function qualificationStatistics(?AffiliateAccount $account): array
    {
        $pending = $account?->referrals()->where('status', AffiliateReferral::STATUS_PENDING)->count() ?? 0;
        $qualified = $account?->referrals()->where('status', AffiliateReferral::STATUS_QUALIFIED)->count() ?? 0;
        $rejected = $account?->referrals()->where('status', AffiliateReferral::STATUS_REJECTED)->count() ?? 0;
        $total = $pending + $qualified + $rejected;

        return [
            'total' => $total,
            'pending' => $pending,
            'qualified' => $qualified,
            'rejected' => $rejected,
            'qualification_rate' => $total > 0 ? round(($qualified / $total) * 100, 2) : 0.0,
        ];
    }

    private function rewardStatistics(?AffiliateAccount $account): array
    {
        $base = $account?->rewards();

        return [
            'total' => $base ? (clone $base)->count() : 0,
            'pending' => $base ? (clone $base)->where('status', AffiliateReward::STATUS_PENDING)->count() : 0,
            'approved' => $base ? (clone $base)->where('status', AffiliateReward::STATUS_APPROVED)->count() : 0,
            'issued' => $base ? (clone $base)->where('status', AffiliateReward::STATUS_ISSUED)->count() : 0,
            'paid' => $base ? (clone $base)->where('status', AffiliateReward::STATUS_PAID)->count() : 0,
            'redeemed' => $base ? (clone $base)->where('status', AffiliateReward::STATUS_REDEEMED)->count() : 0,
            'expired' => $base ? (clone $base)->where('status', AffiliateReward::STATUS_EXPIRED)->count() : 0,
            'cancelled' => $base ? (clone $base)->where('status', AffiliateReward::STATUS_CANCELLED)->count() : 0,
            'voided' => $base ? (clone $base)->where('status', AffiliateReward::STATUS_VOIDED)->count() : 0,
            'membership_credits_available' => $base ? (clone $base)
                ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                ->where('status', AffiliateReward::STATUS_ISSUED)
                ->whereNull('redeemed_at')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count() : 0,
            'membership_credit_days_available' => $base ? (int) (clone $base)
                ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                ->where('status', AffiliateReward::STATUS_ISSUED)
                ->whereNull('redeemed_at')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->sum('membership_credit_days') : 0,
            'total_amount_cents' => $base ? (int) (clone $base)->sum('amount_cents') : 0,
            'total_amount_label' => $base ? Number::currency(((int) (clone $base)->sum('amount_cents')) / 100, 'USD') : '$0.00',
            'total_points' => $base ? (int) (clone $base)->sum('points') : 0,
        ];
    }

    private function payoutStatistics(?AffiliateAccount $account): array
    {
        $base = $account?->payouts();

        return [
            'total' => $base ? (clone $base)->count() : 0,
            'requested' => $base ? (clone $base)->where('status', AffiliatePayout::STATUS_REQUESTED)->count() : 0,
            'approved' => $base ? (clone $base)->where('status', AffiliatePayout::STATUS_APPROVED)->count() : 0,
            'processing' => $base ? (clone $base)->where('status', AffiliatePayout::STATUS_PROCESSING)->count() : 0,
            'paid' => $base ? (clone $base)->where('status', AffiliatePayout::STATUS_PAID)->count() : 0,
            'rejected' => $base ? (clone $base)->where('status', AffiliatePayout::STATUS_REJECTED)->count() : 0,
            'cancelled' => $base ? (clone $base)->where('status', AffiliatePayout::STATUS_CANCELLED)->count() : 0,
            'total_requested_cents' => $base ? (int) (clone $base)->sum('amount_cents') : 0,
            'total_paid_cents' => $base ? (int) (clone $base)->where('status', AffiliatePayout::STATUS_PAID)->sum('amount_cents') : 0,
        ];
    }

    private function payoutHistory(?AffiliateAccount $account, AffiliatePayoutService $payouts, int $limit = 10): array
    {
        return $account ? $account->payouts()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AffiliatePayout $payout): array => $payouts->payoutPayload($payout))
            ->values()
            ->all() : [];
    }

    private function referralActivity(?AffiliateAccount $account): array
    {
        if (! $account) {
            return [];
        }

        $visits = $account->referralVisits()
            ->with('referralCode:id,code')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($visit): array => [
                'type' => 'referral_visit',
                'label' => 'Referral Visit',
                'description' => ($visit->referralCode?->code ?? 'Referral code').' opened a BlendBeats page.',
                'occurred_at' => $visit->visited_at?->toISOString(),
                'metadata' => [
                    'landing_url' => $visit->landing_url,
                    'referral_code' => $visit->referralCode?->code,
                ],
            ]);

        $referrals = $account->referrals()
            ->with(['referredUser:id,name,email', 'referralCode:id,code'])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (AffiliateReferral $referral): array => [
                'type' => 'signup_attributed',
                'label' => 'Signup Attributed',
                'description' => ($referral->referredUser?->name ?? $referral->referredUser?->email ?? 'New user').' joined from '.$referral->referralCode?->code.'.',
                'occurred_at' => $referral->attributed_at?->toISOString(),
                'metadata' => [
                    'status' => $referral->status,
                    'referral_code' => $referral->referralCode?->code,
                ],
            ]);

        return $visits
            ->toBase()
            ->merge($referrals->toBase())
            ->sortByDesc('occurred_at')
            ->take(10)
            ->values()
            ->all();
    }

    private function rewardActivity(?AffiliateAccount $account): array
    {
        if (! $account) {
            return [];
        }

        return $account->rewards()
            ->with(['audits' => fn ($query) => $query->latest('id')->limit(5)])
            ->latest('id')
            ->limit(10)
            ->get()
            ->flatMap(fn (AffiliateReward $reward) => $reward->audits->map(fn ($audit): array => [
                'type' => 'reward_'.$audit->action,
                'label' => 'Reward '.str($audit->action)->headline()->toString(),
                'description' => str($reward->reward_type)->headline()->toString().' reward moved to '.($audit->to_status ?? $reward->status).'.',
                'occurred_at' => $audit->occurred_at?->toISOString(),
                'metadata' => [
                    'reward_id' => $reward->id,
                    'reward_type' => $reward->reward_type,
                    'from_status' => $audit->from_status,
                    'to_status' => $audit->to_status,
                ],
            ]))
            ->sortByDesc('occurred_at')
            ->take(10)
            ->values()
            ->all();
    }

    private function referralPayload(AffiliateReferral $referral): array
    {
        return [
            'id' => $referral->id,
            'status' => $referral->status,
            'attribution_type' => $referral->attribution_type,
            'referral_code' => $referral->referralCode?->code,
            'referred_user' => $referral->referredUser ? [
                'id' => $referral->referredUser->id,
                'name' => $referral->referredUser->name,
                'email' => $referral->referredUser->email,
            ] : null,
            'attributed_at' => $referral->attributed_at?->toISOString(),
            'qualified_at' => $referral->qualified_at?->toISOString(),
            'qualified_transaction_type' => $referral->qualified_transaction_type,
            'qualified_transaction_id' => $referral->qualified_transaction_id,
            'rejected_at' => $referral->rejected_at?->toISOString(),
            'rejection_reason' => $referral->rejection_reason,
            'is_suspicious' => $referral->is_suspicious,
            'fraud_reason' => $referral->fraud_reason,
            'fraud_flags' => $referral->fraud_flags ?? [],
            'visit' => $referral->referralVisit ? [
                'id' => $referral->referralVisit->id,
                'visited_at' => $referral->referralVisit->visited_at?->toISOString(),
                'landing_url' => $referral->referralVisit->landing_url,
                'is_suspicious' => $referral->referralVisit->is_suspicious,
                'suspicious_reason' => $referral->referralVisit->suspicious_reason,
            ] : null,
        ];
    }

    private function rewardPayload(AffiliateReward $reward): array
    {
        return [
            'id' => $reward->id,
            'affiliate_referral_id' => $reward->affiliate_referral_id,
            'reward_type' => $reward->reward_type,
            'source' => $reward->source,
            'status' => $reward->status,
            'amount_cents' => $reward->amount_cents,
            'amount_label' => $reward->amount_cents !== null ? Number::currency($reward->amount_cents / 100, $reward->currency) : null,
            'currency' => $reward->currency,
            'points' => $reward->points,
            'quantity' => $reward->quantity,
            'membership_credit_days' => $reward->membership_credit_days,
            'available_at' => $reward->available_at?->toISOString(),
            'expires_at' => $reward->expires_at?->toISOString(),
            'approved_at' => $reward->approved_at?->toISOString(),
            'issued_at' => $reward->issued_at?->toISOString(),
            'paid_at' => $reward->paid_at?->toISOString(),
            'redeemed_at' => $reward->redeemed_at?->toISOString(),
            'issued_reference' => $reward->issued_reference,
            'redeemed_membership_expires_at' => $reward->metadata['membership_expires_at'] ?? null,
            'can_redeem' => $reward->reward_type === AffiliateReward::TYPE_MEMBERSHIP_CREDIT
                && $reward->status === AffiliateReward::STATUS_ISSUED
                && ! $reward->redeemed_at
                && (! $reward->expires_at || $reward->expires_at->gt(now())),
            'is_expired' => $reward->reward_type === AffiliateReward::TYPE_MEMBERSHIP_CREDIT
                && ! $reward->redeemed_at
                && $reward->expires_at
                && $reward->expires_at->lte(now()),
            'referred_user' => $reward->referral?->referredUser ? [
                'id' => $reward->referral->referredUser->id,
                'name' => $reward->referral->referredUser->name,
                'email' => $reward->referral->referredUser->email,
            ] : null,
            'audits' => $reward->audits
                ->map(fn ($audit): array => [
                    'id' => $audit->id,
                    'action' => $audit->action,
                    'from_status' => $audit->from_status,
                    'to_status' => $audit->to_status,
                    'occurred_at' => $audit->occurred_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function referralLink(AffiliateAccount $account): ?string
    {
        $code = $account->defaultReferralCode?->code;

        if (! $code) {
            return null;
        }

        return url('/register').'?ref='.rawurlencode($code);
    }
}
