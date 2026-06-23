<?php

namespace App\Services;

use App\Models\AffiliateAccount;
use App\Models\AffiliateCampaign;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralVisit;
use App\Models\AffiliateReward;

class AffiliateAnalyticsService
{
    public function report(int $leaderboardLimit = 10): array
    {
        $totalVisits = AffiliateReferralVisit::query()->count();
        $totalSignups = AffiliateReferral::query()->count();
        $qualifiedReferrals = AffiliateReferral::query()
            ->where('status', AffiliateReferral::STATUS_QUALIFIED)
            ->count();

        return [
            'statistics' => [
                'total_affiliates' => AffiliateAccount::query()->count(),
                'active_affiliates' => AffiliateAccount::query()
                    ->where('status', AffiliateAccount::STATUS_ACTIVE)
                    ->count(),
                'total_referral_visits' => $totalVisits,
                'total_attributed_signups' => $totalSignups,
                'total_qualified_referrals' => $qualifiedReferrals,
                'total_membership_credits_issued' => $this->membershipCreditsIssuedCount(),
                'total_membership_credits_redeemed' => AffiliateReward::query()
                    ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                    ->where(function ($query): void {
                        $query
                            ->where('status', AffiliateReward::STATUS_REDEEMED)
                            ->orWhereNotNull('redeemed_at');
                    })
                    ->count(),
                'total_membership_credits_expired' => AffiliateReward::query()
                    ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                    ->where('status', AffiliateReward::STATUS_EXPIRED)
                    ->count(),
                'total_payable_balance_cents' => $this->payableBalanceCents(),
                'total_payouts_requested' => AffiliatePayout::query()->count(),
                'total_payouts_paid' => AffiliatePayout::query()
                    ->where('status', AffiliatePayout::STATUS_PAID)
                    ->count(),
                'total_payout_amount_paid_cents' => AffiliatePayout::query()
                    ->where('status', AffiliatePayout::STATUS_PAID)
                    ->sum('amount_cents'),
            ],
            'conversion_rates' => [
                'visit_to_signup_rate' => $this->rate($totalSignups, $totalVisits),
                'signup_to_qualified_rate' => $this->rate($qualifiedReferrals, $totalSignups),
                'visit_to_qualified_rate' => $this->rate($qualifiedReferrals, $totalVisits),
            ],
            'top_affiliates' => $this->topAffiliates($leaderboardLimit),
            'campaigns' => $this->campaigns(),
            'payouts' => $this->payouts(),
        ];
    }

    private function topAffiliates(int $limit): array
    {
        return AffiliateAccount::query()
            ->with(['user:id,name,email', 'defaultReferralCode:id,affiliate_account_id,code'])
            ->withCount([
                'referralVisits as referral_visits_count',
                'referrals as attributed_signups_count',
                'referrals as qualified_referrals_count' => fn ($query) => $query->where('status', AffiliateReferral::STATUS_QUALIFIED),
                'rewards as membership_credits_issued_count' => fn ($query) => $query
                    ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                    ->whereNotNull('issued_at'),
                'rewards as membership_credits_redeemed_count' => fn ($query) => $query
                    ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                    ->where(function ($query): void {
                        $query
                            ->where('status', AffiliateReward::STATUS_REDEEMED)
                            ->orWhereNotNull('redeemed_at');
                    }),
                'rewards as membership_credits_expired_count' => fn ($query) => $query
                    ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                    ->where('status', AffiliateReward::STATUS_EXPIRED),
            ])
            ->orderByDesc('qualified_referrals_count')
            ->orderByDesc('attributed_signups_count')
            ->orderByDesc('referral_visits_count')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (AffiliateAccount $account, int $index): array => [
                'rank' => $index + 1,
                'affiliate_account_id' => $account->id,
                'display_name' => $account->display_name ?: $account->user?->name,
                'contact_email' => $account->contact_email ?: $account->user?->email,
                'status' => $account->status,
                'referral_code' => $account->defaultReferralCode?->code,
                'referral_visits' => (int) $account->referral_visits_count,
                'attributed_signups' => (int) $account->attributed_signups_count,
                'qualified_referrals' => (int) $account->qualified_referrals_count,
                'membership_credits_issued' => (int) $account->membership_credits_issued_count,
                'membership_credits_redeemed' => (int) $account->membership_credits_redeemed_count,
                'membership_credits_expired' => (int) $account->membership_credits_expired_count,
                'visit_to_signup_rate' => $this->rate(
                    (int) $account->attributed_signups_count,
                    (int) $account->referral_visits_count,
                ),
                'signup_to_qualified_rate' => $this->rate(
                    (int) $account->qualified_referrals_count,
                    (int) $account->attributed_signups_count,
                ),
            ])
            ->values()
            ->all();
    }

    private function campaigns(): array
    {
        return AffiliateCampaign::query()
            ->withCount([
                'referralCodes as referral_codes_count',
                'referralVisits as referral_visits_count',
                'referrals as attributed_signups_count',
                'qualifiedReferrals as qualified_referrals_count',
                'rewards as membership_credits_issued_count' => fn ($query) => $query
                    ->where('affiliate_rewards.reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                    ->whereNotNull('affiliate_rewards.issued_at'),
                'rewards as membership_credits_redeemed_count' => fn ($query) => $query
                    ->where('affiliate_rewards.reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                    ->where(function ($query): void {
                        $query
                            ->where('affiliate_rewards.status', AffiliateReward::STATUS_REDEEMED)
                            ->orWhereNotNull('affiliate_rewards.redeemed_at');
                    }),
                'rewards as membership_credits_expired_count' => fn ($query) => $query
                    ->where('affiliate_rewards.reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
                    ->where('affiliate_rewards.status', AffiliateReward::STATUS_EXPIRED),
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'paused' THEN 2 WHEN 'ended' THEN 3 ELSE 4 END")
            ->latest('starts_at')
            ->latest('id')
            ->get()
            ->map(fn (AffiliateCampaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'status' => $campaign->status,
                'starts_at' => $campaign->starts_at?->toISOString(),
                'ends_at' => $campaign->ends_at?->toISOString(),
                'referral_codes' => (int) $campaign->referral_codes_count,
                'referral_visits' => (int) $campaign->referral_visits_count,
                'attributed_signups' => (int) $campaign->attributed_signups_count,
                'qualified_referrals' => (int) $campaign->qualified_referrals_count,
                'membership_credits_issued' => (int) $campaign->membership_credits_issued_count,
                'membership_credits_redeemed' => (int) $campaign->membership_credits_redeemed_count,
                'membership_credits_expired' => (int) $campaign->membership_credits_expired_count,
                'visit_to_signup_rate' => $this->rate(
                    (int) $campaign->attributed_signups_count,
                    (int) $campaign->referral_visits_count,
                ),
                'signup_to_qualified_rate' => $this->rate(
                    (int) $campaign->qualified_referrals_count,
                    (int) $campaign->attributed_signups_count,
                ),
                'visit_to_qualified_rate' => $this->rate(
                    (int) $campaign->qualified_referrals_count,
                    (int) $campaign->referral_visits_count,
                ),
            ])
            ->values()
            ->all();
    }

    private function membershipCreditsIssuedCount(): int
    {
        return AffiliateReward::query()
            ->where('reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
            ->whereNotNull('issued_at')
            ->count();
    }

    private function payouts(): array
    {
        return [
            'payable_balance_cents' => $this->payableBalanceCents(),
            'requested_count' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_REQUESTED)->count(),
            'approved_count' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_APPROVED)->count(),
            'processing_count' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_PROCESSING)->count(),
            'paid_count' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_PAID)->count(),
            'rejected_count' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_REJECTED)->count(),
            'cancelled_count' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_CANCELLED)->count(),
            'requested_amount_cents' => AffiliatePayout::query()->sum('amount_cents'),
            'approved_amount_cents' => AffiliatePayout::query()
                ->whereIn('status', [
                    AffiliatePayout::STATUS_APPROVED,
                    AffiliatePayout::STATUS_PROCESSING,
                ])
                ->sum('amount_cents'),
            'paid_amount_cents' => AffiliatePayout::query()
                ->where('status', AffiliatePayout::STATUS_PAID)
                ->sum('amount_cents'),
        ];
    }

    private function payableBalanceCents(): int
    {
        return (int) AffiliateReward::query()
            ->where('status', AffiliateReward::STATUS_APPROVED)
            ->where('amount_cents', '>', 0)
            ->whereNull('affiliate_payout_id')
            ->sum('amount_cents');
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0
            ? round(($numerator / $denominator) * 100, 2)
            : 0.0;
    }
}
