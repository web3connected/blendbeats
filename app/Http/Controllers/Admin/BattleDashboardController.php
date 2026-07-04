<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AffiliateCampaign;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralVisit;
use App\Models\AffiliateRewardAudit;
use App\Models\BattleEscrow;
use App\Models\DjBattle;
use App\Models\DjBattleVote;
use App\Models\DjProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BattleDashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->startOfDay();
        $openBattleStatuses = [
            DjBattle::STATUS_ACCEPTED,
            DjBattle::STATUS_RECORDING,
        ];
        $reviewEscrows = BattleEscrow::query()
            ->where('requires_admin_review', true)
            ->count();
        $settlementFailures = BattleEscrow::query()
            ->whereNotNull('last_settlement_error')
            ->count();
        $battlesBelowVoteMinimum = $this->battlesBelowVoteMinimum();
        $pendingWithdrawals = AffiliatePayout::query()
            ->whereIn('status', [
                AffiliatePayout::STATUS_REQUESTED,
                AffiliatePayout::STATUS_APPROVED,
                AffiliatePayout::STATUS_PROCESSING,
            ])
            ->count();
        $suspiciousVoteClusters = $this->suspiciousVoteClusters();
        $submittedVotes = DjBattleVote::query()
            ->whereNotNull('submitted_at')
            ->count();
        $battleVoteBase = DjBattle::query()
            ->whereIn('status', [DjBattle::STATUS_VOTING, DjBattle::STATUS_COMPLETED])
            ->count();
        $averageVotesPerBattle = $battleVoteBase > 0
            ? round($submittedVotes / $battleVoteBase, 1)
            : 0;

        return view('admin.battles.dashboard', [
            'adminAlerts' => [
                [
                    'label' => 'Escrows Require Review',
                    'value' => $reviewEscrows,
                    'detail' => 'Escrows waiting for admin review.',
                    'theme' => $reviewEscrows > 0 ? 'danger' : 'success',
                    'icon' => 'fas fa-shield-alt',
                ],
                [
                    'label' => 'Settlement Jobs Failed',
                    'value' => $settlementFailures,
                    'detail' => 'Escrows with settlement errors.',
                    'theme' => $settlementFailures > 0 ? 'danger' : 'success',
                    'icon' => 'fas fa-exclamation-triangle',
                ],
                [
                    'label' => 'Battles Below Vote Minimum',
                    'value' => $battlesBelowVoteMinimum,
                    'detail' => 'Voting battles below minimum vote count.',
                    'theme' => $battlesBelowVoteMinimum > 0 ? 'warning' : 'success',
                    'icon' => 'fas fa-vote-yea',
                ],
                [
                    'label' => 'Withdrawals Pending Approval',
                    'value' => $pendingWithdrawals,
                    'detail' => 'Affiliate payout requests needing movement.',
                    'theme' => $pendingWithdrawals > 0 ? 'warning' : 'success',
                    'icon' => 'fas fa-hand-holding-usd',
                ],
                [
                    'label' => 'Suspicious Vote Clusters',
                    'value' => $suspiciousVoteClusters,
                    'detail' => 'Users with 5+ votes in the last 24 hours.',
                    'theme' => $suspiciousVoteClusters > 0 ? 'warning' : 'success',
                    'icon' => 'fas fa-user-secret',
                ],
            ],
            'sections' => [
                [
                    'title' => 'Platform Overview',
                    'cards' => [
                        $this->metric('Total Users', User::query()->count(), 'fas fa-users', 'info'),
                        $this->metric('Active DJs', DjProfile::query()->where('profile_status', 'active')->count(), 'fas fa-headphones', 'success'),
                        $this->metric('Battle-Enabled DJs', DjProfile::query()->where('battle_enabled', true)->count(), 'fas fa-bolt', 'primary'),
                    ],
                ],
                [
                    'title' => 'Battle Activity',
                    'cards' => [
                        $this->metric('Open Battles', DjBattle::query()->whereIn('status', $openBattleStatuses)->count(), 'fas fa-fire', 'danger'),
                        $this->metric('Voting Battles', DjBattle::query()->where('status', DjBattle::STATUS_VOTING)->count(), 'fas fa-vote-yea', 'warning'),
                        $this->metric('Completed Battles', DjBattle::query()->where('status', DjBattle::STATUS_COMPLETED)->count(), 'fas fa-trophy', 'success'),
                        $this->metric('Pending Challenges', DjBattle::query()->where('status', DjBattle::STATUS_PENDING)->count(), 'fas fa-hourglass-half', 'secondary'),
                        $this->metric('Disputed Battles', DjBattle::query()->where('status', DjBattle::STATUS_DISPUTED)->count(), 'fas fa-gavel', 'danger'),
                    ],
                ],
                [
                    'title' => 'Wallet & Token Economy',
                    'cards' => [
                        $this->metric('Total Tokens Issued', (int) Wallet::query()->sum('lifetime_earned'), 'fas fa-coins', 'info'),
                        $this->metric('Total Tokens Locked', (int) Wallet::query()->sum('locked_balance'), 'fas fa-lock', 'warning'),
                        $this->metric('Total Tokens Spent', (int) Wallet::query()->sum('lifetime_spent'), 'fas fa-receipt', 'primary'),
                        $this->metric('Total Tokens Withdrawn', (int) Wallet::query()->sum('lifetime_withdrawn'), 'fas fa-money-bill-wave', 'secondary'),
                        $this->metric('Pending Withdrawals', $pendingWithdrawals, 'fas fa-hand-holding-usd', $pendingWithdrawals > 0 ? 'warning' : 'success'),
                        $this->metric('Failed Wallet Transactions', $this->failedWalletTransactions(), 'fas fa-times-circle', 'danger'),
                    ],
                ],
                [
                    'title' => 'Escrow / Settlement Health',
                    'cards' => [
                        $this->metric('Escrows Requiring Review', $reviewEscrows, 'fas fa-shield-alt', $reviewEscrows > 0 ? 'danger' : 'success'),
                        $this->metric('Settlement Failures', $settlementFailures, 'fas fa-exclamation-triangle', $settlementFailures > 0 ? 'danger' : 'success'),
                        $this->metric('Cancelled Battles', DjBattle::query()->where('status', DjBattle::STATUS_CANCELLED)->count(), 'fas fa-ban', 'secondary'),
                        $this->metric('Refunded Battles', BattleEscrow::query()->whereNotNull('refunded_at')->count(), 'fas fa-undo-alt', 'info'),
                    ],
                ],
                [
                    'title' => 'Fan Voting Activity',
                    'cards' => [
                        $this->metric('Votes Cast Today', DjBattleVote::query()->whereNotNull('submitted_at')->where('submitted_at', '>=', $today)->count(), 'fas fa-check-square', 'success'),
                        $this->metric('Average Votes Per Battle', $averageVotesPerBattle, 'fas fa-chart-line', 'info'),
                        $this->metric('Battles Below Vote Minimum', $battlesBelowVoteMinimum, 'fas fa-battery-quarter', $battlesBelowVoteMinimum > 0 ? 'warning' : 'success'),
                        $this->metric('Fan Rewards Paid', (int) WalletTransaction::query()->where('type', WalletService::TYPE_FAN_REWARD)->sum('amount'), 'fas fa-gift', 'primary'),
                    ],
                ],
                [
                    'title' => 'Risk / Admin Review',
                    'cards' => [
                        $this->metric('Suspicious Vote Activity', $suspiciousVoteClusters, 'fas fa-user-secret', $suspiciousVoteClusters > 0 ? 'warning' : 'success'),
                        $this->metric('Duplicate/Flagged Accounts', $this->flaggedAccounts(), 'fas fa-user-lock', 'danger'),
                        $this->metric('High-Stake Battles', DjBattle::query()->where('stake_amount', '>=', 1000)->count(), 'fas fa-arrow-up', 'warning'),
                        $this->metric('Admin Actions Today', $this->adminActionsToday($today), 'fas fa-user-cog', 'info'),
                    ],
                ],
            ],
            'topVotedBattles' => $this->topVotedBattles(),
            'mostActiveVoters' => $this->mostActiveVoters(),
            'generatedAt' => now(),
        ]);
    }

    private function metric(string $label, int|float|string $value, string $icon, string $theme): array
    {
        return [
            'label' => $label,
            'value' => is_int($value) || is_float($value) ? number_format($value, is_float($value) ? 1 : 0) : $value,
            'icon' => $icon,
            'theme' => $theme,
        ];
    }

    private function battlesBelowVoteMinimum(): int
    {
        return DjBattle::query()
            ->where('status', DjBattle::STATUS_VOTING)
            ->whereRaw('(select count(*) from dj_battle_votes where dj_battle_votes.battle_id = dj_battles.id and dj_battle_votes.submitted_at is not null) < dj_battles.minimum_votes')
            ->count();
    }

    private function failedWalletTransactions(): int
    {
        return WalletTransaction::query()
            ->where(fn ($query) => $query
                ->where('status', 'failed')
                ->orWhereNotNull('failed_at'))
            ->count();
    }

    private function suspiciousVoteClusters(): int
    {
        $clusters = DjBattleVote::query()
            ->select('user_id')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', now()->subDay())
            ->groupBy('user_id')
            ->havingRaw('count(*) >= 5');

        return DB::query()
            ->fromSub($clusters, 'vote_clusters')
            ->count();
    }

    private function flaggedAccounts(): int
    {
        return AffiliateReferral::query()->where('is_suspicious', true)->count()
            + AffiliateReferralVisit::query()->where('is_suspicious', true)->count();
    }

    private function adminActionsToday(Carbon $today): int
    {
        return WalletTransaction::query()
            ->whereNotNull('created_by_admin_id')
            ->where('created_at', '>=', $today)
            ->count()
            + AffiliateRewardAudit::query()
                ->where('actor_type', Admin::class)
                ->where('occurred_at', '>=', $today)
                ->count()
            + AffiliatePayout::query()
                ->whereNotNull('processed_by_admin_id')
                ->where('updated_at', '>=', $today)
                ->count()
            + AffiliateCampaign::query()
                ->whereNotNull('created_by_admin_id')
                ->where('created_at', '>=', $today)
                ->count();
    }

    private function topVotedBattles()
    {
        return DjBattle::query()
            ->withCount(['votes as submitted_votes_count' => fn ($query) => $query->whereNotNull('submitted_at')])
            ->orderByDesc('submitted_votes_count')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'uuid', 'title', 'status', 'minimum_votes']);
    }

    private function mostActiveVoters()
    {
        return User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->join('dj_battle_votes', 'dj_battle_votes.user_id', '=', 'users.id')
            ->whereNotNull('dj_battle_votes.submitted_at')
            ->selectRaw('count(*) as submitted_votes_count')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('submitted_votes_count')
            ->orderBy('users.name')
            ->limit(5)
            ->get();
    }
}
