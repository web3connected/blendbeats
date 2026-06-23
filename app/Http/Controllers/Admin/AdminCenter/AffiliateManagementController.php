<?php

namespace App\Http\Controllers\Admin\AdminCenter;

use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\AffiliateCampaign;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\AffiliateReward;
use App\Services\AffiliateAnalyticsService;
use App\Services\AffiliatePayoutService;
use App\Services\AffiliateProgramSettings;
use App\Services\AffiliateReferralQualificationService;
use App\Services\AffiliateRewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateManagementController extends Controller
{
    private const AFFILIATE_STATUSES = [
        AffiliateAccount::STATUS_ACTIVE,
        AffiliateAccount::STATUS_PAUSED,
        AffiliateAccount::STATUS_BANNED,
    ];

    private const REFERRAL_STATUSES = [
        AffiliateReferral::STATUS_PENDING,
        AffiliateReferral::STATUS_QUALIFIED,
        AffiliateReferral::STATUS_REJECTED,
    ];

    private const REWARD_STATUSES = [
        AffiliateReward::STATUS_PENDING,
        AffiliateReward::STATUS_APPROVED,
        AffiliateReward::STATUS_ISSUED,
        AffiliateReward::STATUS_PAID,
        AffiliateReward::STATUS_REDEEMED,
        AffiliateReward::STATUS_EXPIRED,
        AffiliateReward::STATUS_CANCELLED,
        AffiliateReward::STATUS_VOIDED,
    ];

    private const CAMPAIGN_STATUSES = [
        AffiliateCampaign::STATUS_DRAFT,
        AffiliateCampaign::STATUS_ACTIVE,
        AffiliateCampaign::STATUS_PAUSED,
        AffiliateCampaign::STATUS_ENDED,
        AffiliateCampaign::STATUS_ARCHIVED,
    ];

    private const PAYOUT_STATUSES = [
        AffiliatePayout::STATUS_REQUESTED,
        AffiliatePayout::STATUS_APPROVED,
        AffiliatePayout::STATUS_PROCESSING,
        AffiliatePayout::STATUS_PAID,
        AffiliatePayout::STATUS_REJECTED,
        AffiliatePayout::STATUS_CANCELLED,
    ];

    public function affiliates(Request $request): View
    {
        $filters = $this->validateListFilters($request, self::AFFILIATE_STATUSES);
        $search = trim((string) ($filters['search'] ?? ''));

        $affiliates = AffiliateAccount::query()
            ->with(['user', 'defaultReferralCode'])
            ->withCount([
                'referrals',
                'rewards',
                'referrals as qualified_referrals_count' => fn ($query) => $query->where('status', AffiliateReferral::STATUS_QUALIFIED),
            ])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('display_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('defaultReferralCode', fn ($query) => $query->where('code', 'like', "%{$search}%"));
                });
            })
            ->latest('joined_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.affiliate-management.affiliates', [
            'affiliates' => $affiliates,
            'filters' => $filters,
            'statuses' => self::AFFILIATE_STATUSES,
            'stats' => [
                'total' => AffiliateAccount::query()->count(),
                'active' => AffiliateAccount::query()->where('status', AffiliateAccount::STATUS_ACTIVE)->count(),
                'paused' => AffiliateAccount::query()->where('status', AffiliateAccount::STATUS_PAUSED)->count(),
                'banned' => AffiliateAccount::query()->where('status', AffiliateAccount::STATUS_BANNED)->count(),
                'referrals' => AffiliateReferral::query()->count(),
                'qualified_referrals' => AffiliateReferral::query()->where('status', AffiliateReferral::STATUS_QUALIFIED)->count(),
                'rewards' => AffiliateReward::query()->count(),
            ],
        ]);
    }

    public function settings(AffiliateProgramSettings $settings): View
    {
        return view('admin.affiliate-management.settings', [
            'settings' => $settings->toArray(),
        ]);
    }

    public function analytics(AffiliateAnalyticsService $analytics): View
    {
        return view('admin.affiliate-management.analytics', [
            'analytics' => $analytics->report(10),
        ]);
    }

    public function campaigns(Request $request, AffiliateAnalyticsService $analytics): View
    {
        $filters = $this->validateListFilters($request, self::CAMPAIGN_STATUSES);
        $search = trim((string) ($filters['search'] ?? ''));

        $campaigns = AffiliateCampaign::query()
            ->withCount([
                'referralCodes',
                'referralVisits',
                'referrals',
                'qualifiedReferrals',
            ])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'paused' THEN 2 WHEN 'ended' THEN 3 ELSE 4 END")
            ->latest('starts_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.affiliate-management.campaigns', [
            'campaigns' => $campaigns,
            'campaignOptions' => AffiliateCampaign::query()
                ->orderBy('name')
                ->get(['id', 'name', 'status']),
            'codes' => AffiliateReferralCode::query()
                ->with(['affiliateAccount.user', 'campaign'])
                ->orderBy('code')
                ->limit(50)
                ->get(),
            'filters' => $filters,
            'statuses' => self::CAMPAIGN_STATUSES,
            'analytics' => $analytics->report(10)['campaigns'],
            'stats' => [
                'total' => AffiliateCampaign::query()->count(),
                'active' => AffiliateCampaign::query()->where('status', AffiliateCampaign::STATUS_ACTIVE)->count(),
                'paused' => AffiliateCampaign::query()->where('status', AffiliateCampaign::STATUS_PAUSED)->count(),
                'codes' => AffiliateReferralCode::query()->whereNotNull('affiliate_campaign_id')->count(),
            ],
        ]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $validated = $this->validateCampaign($request);

        AffiliateCampaign::query()->create([
            ...$validated,
            'slug' => $this->uniqueCampaignSlug($validated['slug'] ?? null, $validated['name']),
            'created_by_admin_id' => $request->user('admin')?->getKey(),
        ]);

        return redirect()
            ->route('admin.admincenter.affiliatecampaigns.index')
            ->with('status', 'Affiliate campaign created.');
    }

    public function updateCampaign(Request $request, AffiliateCampaign $campaign): RedirectResponse
    {
        $validated = $this->validateCampaign($request, $campaign);

        $campaign->forceFill([
            ...$validated,
            'slug' => $this->uniqueCampaignSlug($validated['slug'] ?? null, $validated['name'], $campaign),
        ])->save();

        return redirect()
            ->route('admin.admincenter.affiliatecampaigns.index')
            ->with('status', 'Affiliate campaign updated.');
    }

    public function updateReferralCodeCampaign(Request $request, AffiliateReferralCode $code): RedirectResponse
    {
        $validated = $request->validate([
            'affiliate_campaign_id' => ['nullable', 'integer', Rule::exists('affiliate_campaigns', 'id')],
        ]);

        $code->forceFill([
            'affiliate_campaign_id' => $validated['affiliate_campaign_id'] ?? null,
        ])->save();

        return redirect()
            ->route('admin.admincenter.affiliatecampaigns.index')
            ->with('status', 'Referral code campaign assignment updated.');
    }

    public function updateAffiliateStatus(Request $request, AffiliateAccount $affiliate): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::AFFILIATE_STATUSES)],
        ]);

        $status = $validated['status'];

        $affiliate->forceFill([
            'status' => $status,
            'approved_at' => $status === AffiliateAccount::STATUS_ACTIVE ? ($affiliate->approved_at ?? now()) : $affiliate->approved_at,
            'paused_at' => $status === AffiliateAccount::STATUS_PAUSED ? now() : null,
            'banned_at' => $status === AffiliateAccount::STATUS_BANNED ? now() : null,
        ])->save();

        return redirect()
            ->route('admin.admincenter.affiliates.index', $request->only(['search', 'status']))
            ->with('status', 'Affiliate status updated.');
    }

    public function referrals(Request $request): View
    {
        $filters = $this->validateListFilters($request, self::REFERRAL_STATUSES);
        $search = trim((string) ($filters['search'] ?? ''));

        $referrals = AffiliateReferral::query()
            ->with(['affiliateAccount.user', 'referralCode', 'referredUser', 'referralVisit'])
            ->withCount(['events', 'rewards'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('qualified_transaction_id', 'like', "%{$search}%")
                        ->orWhere('rejection_reason', 'like', "%{$search}%")
                        ->orWhere('fraud_reason', 'like', "%{$search}%")
                        ->orWhereHas('referralCode', fn ($query) => $query->where('code', 'like', "%{$search}%"))
                        ->orWhereHas('referralVisit', fn ($query) => $query->where('suspicious_reason', 'like', "%{$search}%"))
                        ->orWhereHas('affiliateAccount', function ($query) use ($search): void {
                            $query
                                ->where('display_name', 'like', "%{$search}%")
                                ->orWhere('contact_email', 'like', "%{$search}%")
                                ->orWhereHas('user', function ($query) use ($search): void {
                                    $query
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('referredUser', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('attributed_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.affiliate-management.referrals', [
            'referrals' => $referrals,
            'filters' => $filters,
            'statuses' => self::REFERRAL_STATUSES,
            'stats' => [
                'total' => AffiliateReferral::query()->count(),
                'pending' => AffiliateReferral::query()->where('status', AffiliateReferral::STATUS_PENDING)->count(),
                'qualified' => AffiliateReferral::query()->where('status', AffiliateReferral::STATUS_QUALIFIED)->count(),
                'rejected' => AffiliateReferral::query()->where('status', AffiliateReferral::STATUS_REJECTED)->count(),
                'suspicious_referrals' => AffiliateReferral::query()->where('is_suspicious', true)->count(),
                'suspicious_visits' => AffiliateReferralVisit::query()->where('is_suspicious', true)->count(),
                'events' => AffiliateReferral::query()->withCount('events')->get()->sum('events_count'),
            ],
        ]);
    }

    public function updateReferralStatus(
        Request $request,
        AffiliateReferral $referral,
        AffiliateReferralQualificationService $qualificationService,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::REFERRAL_STATUSES)],
            'qualified_transaction_type' => ['nullable', 'string', 'max:120'],
            'qualified_transaction_id' => ['nullable', 'string', 'max:255'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['status'] === AffiliateReferral::STATUS_QUALIFIED) {
            $provider = $validated['qualified_transaction_type'] ?: ($referral->qualified_transaction_type ?: 'admin');
            $transactionId = $validated['qualified_transaction_id'] ?: ($referral->qualified_transaction_id ?: 'admin-referral-'.$referral->id.'-'.now()->format('YmdHis'));

            $qualificationService->qualifyReferral(
                referral: $referral,
                provider: $provider,
                transactionId: $transactionId,
                source: 'admin_manual',
                status: 'manual',
                metadata: [
                    'admin_id' => $request->user('admin')?->getKey(),
                    'reason' => $validated['rejection_reason'] ?? null,
                ],
            );
        } else {
            $referral->forceFill([
                'status' => $validated['status'],
                'qualified_at' => null,
                'qualified_transaction_type' => null,
                'qualified_transaction_id' => null,
                'rejected_at' => $validated['status'] === AffiliateReferral::STATUS_REJECTED ? now() : null,
                'rejection_reason' => $this->adminReferralRejectionReason($validated),
                'is_suspicious' => $validated['status'] === AffiliateReferral::STATUS_REJECTED
                    ? true
                    : $referral->is_suspicious,
                'fraud_reason' => $validated['status'] === AffiliateReferral::STATUS_REJECTED
                    ? $this->adminReferralRejectionReason($validated)
                    : $referral->fraud_reason,
                'fraud_flags' => $validated['status'] === AffiliateReferral::STATUS_REJECTED
                    ? array_values(array_unique([
                        ...(array) ($referral->fraud_flags ?? []),
                        'admin_rejected',
                    ]))
                    : $referral->fraud_flags,
                'fraud_checked_at' => $validated['status'] === AffiliateReferral::STATUS_REJECTED
                    ? now()
                    : $referral->fraud_checked_at,
            ])->save();
        }

        return redirect()
            ->route('admin.admincenter.affiliatereferrals.index', $request->only(['search', 'status']))
            ->with('status', 'Referral status updated.');
    }

    public function rewards(Request $request): View
    {
        $filters = $this->validateListFilters($request, self::REWARD_STATUSES);
        $search = trim((string) ($filters['search'] ?? ''));

        $rewards = AffiliateReward::query()
            ->with(['affiliateAccount.user', 'referral.referredUser', 'referral.referralCode'])
            ->withCount('audits')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('reward_type', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%")
                        ->orWhere('issued_reference', 'like', "%{$search}%")
                        ->orWhereHas('affiliateAccount', function ($query) use ($search): void {
                            $query
                                ->where('display_name', 'like', "%{$search}%")
                                ->orWhere('contact_email', 'like', "%{$search}%")
                                ->orWhereHas('user', function ($query) use ($search): void {
                                    $query
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('referral.referredUser', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('referral.referralCode', fn ($query) => $query->where('code', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.affiliate-management.rewards', [
            'rewards' => $rewards,
            'filters' => $filters,
            'statuses' => self::REWARD_STATUSES,
            'stats' => [
                'total' => AffiliateReward::query()->count(),
                'pending' => AffiliateReward::query()->where('status', AffiliateReward::STATUS_PENDING)->count(),
                'approved' => AffiliateReward::query()->where('status', AffiliateReward::STATUS_APPROVED)->count(),
                'issued' => AffiliateReward::query()->where('status', AffiliateReward::STATUS_ISSUED)->count(),
                'paid' => AffiliateReward::query()->where('status', AffiliateReward::STATUS_PAID)->count(),
                'redeemed' => AffiliateReward::query()->where('status', AffiliateReward::STATUS_REDEEMED)->count(),
                'expired' => AffiliateReward::query()->where('status', AffiliateReward::STATUS_EXPIRED)->count(),
            ],
        ]);
    }

    public function updateRewardStatus(
        Request $request,
        AffiliateReward $reward,
        AffiliateRewardService $rewardService,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::REWARD_STATUSES)],
            'issued_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $rewardService->setStatus(
            reward: $reward,
            status: $validated['status'],
            actor: $request->user('admin'),
            metadata: [
                'source' => 'admin_management',
                'issued_reference' => $validated['issued_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
        );

        return redirect()
            ->route('admin.admincenter.affiliaterewards.index', $request->only(['search', 'status']))
            ->with('status', 'Reward status updated.');
    }

    public function payouts(Request $request, AffiliatePayoutService $payoutService): View
    {
        $filters = $this->validateListFilters($request, self::PAYOUT_STATUSES);
        $search = trim((string) ($filters['search'] ?? ''));

        $payouts = AffiliatePayout::query()
            ->with(['affiliateAccount.user', 'requestedByUser', 'processedByAdmin'])
            ->withCount('rewards')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('payment_method', 'like', "%{$search}%")
                        ->orWhere('payout_reference', 'like', "%{$search}%")
                        ->orWhere('rejection_reason', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('affiliateAccount', function ($query) use ($search): void {
                            $query
                                ->where('display_name', 'like', "%{$search}%")
                                ->orWhere('contact_email', 'like', "%{$search}%")
                                ->orWhereHas('user', function ($query) use ($search): void {
                                    $query
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->latest('requested_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.affiliate-management.payouts', [
            'payouts' => $payouts,
            'filters' => $filters,
            'statuses' => self::PAYOUT_STATUSES,
            'stats' => [
                'total' => AffiliatePayout::query()->count(),
                'requested' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_REQUESTED)->count(),
                'approved' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_APPROVED)->count(),
                'processing' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_PROCESSING)->count(),
                'paid' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_PAID)->count(),
                'rejected' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_REJECTED)->count(),
                'cancelled' => AffiliatePayout::query()->where('status', AffiliatePayout::STATUS_CANCELLED)->count(),
                'payable_balance' => $this->money(AffiliateReward::query()
                    ->where('status', AffiliateReward::STATUS_APPROVED)
                    ->where('currency', AffiliatePayoutService::DEFAULT_CURRENCY)
                    ->where('amount_cents', '>', 0)
                    ->whereNull('affiliate_payout_id')
                    ->sum('amount_cents')),
                'requested_amount' => $this->money(AffiliatePayout::query()->sum('amount_cents')),
                'paid_amount' => $this->money(AffiliatePayout::query()
                    ->where('status', AffiliatePayout::STATUS_PAID)
                    ->sum('amount_cents')),
            ],
        ]);
    }

    public function updatePayoutStatus(
        Request $request,
        AffiliatePayout $payout,
        AffiliatePayoutService $payoutService,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::PAYOUT_STATUSES)],
            'payout_reference' => ['nullable', 'string', 'max:255'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payoutService->setStatus(
            payout: $payout,
            status: $validated['status'],
            admin: $request->user('admin'),
            reference: $validated['payout_reference'] ?? null,
            reason: $validated['rejection_reason'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('admin.admincenter.affiliatepayouts.index', $request->only(['search', 'status']))
            ->with('status', 'Payout status updated.');
    }

    private function validateCampaign(Request $request, ?AffiliateCampaign $campaign = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('affiliate_campaigns', 'slug')->ignore($campaign),
            ],
            'status' => ['required', Rule::in(self::CAMPAIGN_STATUSES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function money(int|float|string|null $amountCents, string $currency = AffiliatePayoutService::DEFAULT_CURRENCY): string
    {
        return $currency.' '.number_format(((int) $amountCents) / 100, 2);
    }

    private function adminReferralRejectionReason(array $validated): ?string
    {
        if (($validated['status'] ?? null) !== AffiliateReferral::STATUS_REJECTED) {
            return null;
        }

        return $validated['rejection_reason'] ?: 'Rejected by admin';
    }

    private function uniqueCampaignSlug(?string $slug, string $name, ?AffiliateCampaign $ignore = null): string
    {
        $base = Str::slug($slug ?: $name) ?: 'campaign';
        $candidate = $base;
        $suffix = 2;

        while (
            AffiliateCampaign::query()
                ->where('slug', $candidate)
                ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function validateListFilters(Request $request, array $statuses): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in($statuses)],
        ]);
    }
}
