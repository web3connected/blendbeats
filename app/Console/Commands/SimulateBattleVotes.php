<?php

namespace App\Console\Commands;

use App\Models\DjBattle;
use App\Models\DjBattleEntry;
use App\Models\DjBattleVote;
use App\Models\DjProfile;
use App\Models\User;
use App\Services\DjBattles\DjBattleService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SimulateBattleVotes extends Command
{
    protected $signature = 'battle:simulate-votes
        {--battle= : Battle UUID}
        {--challenger= : Challenger DJ name or handle}
        {--opponent= : Opponent DJ name or handle}
        {--votes=20 : Number of simulated votes to create}
        {--force : Allow production usage and override existing real-vote guard}';

    protected $description = 'Create simulated fan scorecard votes for a DJ battle.';

    private const TEST_EMAIL_DOMAIN = 'blendbeats.test';

    private const SCORE_COLUMNS = [
        'sample_integration' => 'sample_integration_score',
        'scratching_ability' => 'scratching_score',
        'mixing_ability' => 'mixing_score',
        'blending' => 'blending_score',
        'creativity' => 'creativity_score',
        'technical_execution' => 'technical_execution_score',
        'music_selection' => 'track_selection_score',
        'battle_composition' => 'battle_composition_score',
        'entertainment_value' => 'entertainment_value_score',
        'overall_performance' => 'overall_performance_score',
    ];

    public function handle(DjBattleService $battles): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('This command cannot run in production without --force.');

            return self::FAILURE;
        }

        $voteTarget = max(1, min(500, (int) $this->option('votes')));
        $battle = $this->resolveBattle();

        if (! $battle) {
            $this->error('Battle could not be found. Provide --battle=UUID or --challenger and --opponent.');

            return self::FAILURE;
        }

        $battle->loadMissing(['challenger.user', 'opponent.user']);

        if (! $this->isSupportedBattleState($battle)) {
            $this->error("Battle status [{$battle->status}] is not supported for vote simulation.");
            $this->line('Supported states: voting, completed, expired.');

            return self::FAILURE;
        }

        $entries = $this->submittedEntries($battle);

        if ($entries->count() !== 2) {
            $this->error('Both DJs must have submitted battle entries before votes can be simulated.');
            $this->line("Entries found: {$entries->count()}");

            return self::FAILURE;
        }

        $this->info("Battle found: {$battle->challenger->dj_name} vs {$battle->opponent->dj_name}");
        $this->line("Entries found: {$entries->count()}");

        $voters = $this->testVoters($voteTarget);
        $participantUserIds = collect($battle->participantUserIds())->all();
        $usableVoters = $voters
            ->reject(fn (User $user): bool => in_array($user->id, $participantUserIds, true))
            ->values();

        if ($usableVoters->count() !== $voteTarget) {
            $participantVoterCount = $voteTarget - $usableVoters->count();
            $this->warn("Skipped {$participantVoterCount} test voter(s) because they are battle participants.");
        }

        if (! $this->option('force') && $this->hasExistingNonTestVotes($battle, $usableVoters)) {
            $this->error('This battle already has votes from users outside the generated Test Voter set.');
            $this->line('Use --force only if you intentionally want to mix simulated votes into existing voting data.');

            return self::FAILURE;
        }

        $this->line("Creating {$usableVoters->count()} simulated votes...");
        $this->newLine();

        $created = 0;
        $skipped = 0;

        foreach ($usableVoters as $index => $voter) {
            if ($battle->votes()->where('user_id', $voter->id)->exists()) {
                $skipped++;
                $this->warn('Skipped existing vote for '.$voter->name);

                continue;
            }

            $this->createSimulatedVote($battle, $entries, $voter);
            $created++;
            $this->info("Vote {$created} created for {$voter->name}");
        }

        $battle = $battle->refresh();
        $battle = $this->runWinnerCalculation($battle, $battles);
        $totalVotes = $battle->votes()->whereNotNull('submitted_at')->count();

        $this->newLine();
        $this->info('Simulation complete.');
        $this->line("Created votes: {$created}");
        $this->line("Skipped votes: {$skipped}");
        $this->line("Total battle votes: {$totalVotes}");
        $this->line('Winner calculation updated.');

        return self::SUCCESS;
    }

    private function resolveBattle(): ?DjBattle
    {
        $uuid = $this->option('battle');

        if ($uuid) {
            return DjBattle::query()
                ->with(['challenger.user', 'opponent.user', 'entries'])
                ->where('uuid', $uuid)
                ->first();
        }

        $challenger = $this->option('challenger');
        $opponent = $this->option('opponent');

        if (! $challenger || ! $opponent) {
            return null;
        }

        $battle = $this->battleBetweenProfiles($challenger, $opponent);

        return $battle ?: $this->battleBetweenProfiles($opponent, $challenger);
    }

    private function battleBetweenProfiles(string $challenger, string $opponent): ?DjBattle
    {
        return DjBattle::query()
            ->with(['challenger.user', 'opponent.user', 'entries'])
            ->whereHas('challenger', fn ($query) => $this->profileNameQuery($query, $challenger))
            ->whereHas('opponent', fn ($query) => $this->profileNameQuery($query, $opponent))
            ->latest()
            ->first();
    }

    private function profileNameQuery($query, string $value): void
    {
        $normalized = strtolower(trim($value));

        $query->whereRaw('LOWER(dj_name) = ?', [$normalized])
            ->orWhereRaw('LOWER(handle) = ?', [$normalized]);
    }

    private function isSupportedBattleState(DjBattle $battle): bool
    {
        return in_array($battle->status, [
            DjBattle::STATUS_VOTING,
            DjBattle::STATUS_COMPLETED,
            DjBattle::STATUS_EXPIRED,
        ], true);
    }

    /**
     * @return Collection<int, DjBattleEntry>
     */
    private function submittedEntries(DjBattle $battle): Collection
    {
        return $battle->entries()
            ->whereIn('dj_profile_id', [$battle->challenger_dj_profile_id, $battle->opponent_dj_profile_id])
            ->where('status', DjBattleEntry::STATUS_SUBMITTED)
            ->get()
            ->keyBy('dj_profile_id');
    }

    /**
     * @return Collection<int, User>
     */
    private function testVoters(int $count): Collection
    {
        return collect(range(1, $count))
            ->map(function (int $index): User {
                $email = "test-voter-{$index}@".self::TEST_EMAIL_DOMAIN;
                $user = User::query()->firstOrNew(['email' => $email]);

                $user->forceFill([
                    'name' => "Test Voter {$index}",
                    'password' => 'password',
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                return $user;
            });
    }

    /**
     * @param  Collection<int, User>  $testVoters
     */
    private function hasExistingNonTestVotes(DjBattle $battle, Collection $testVoters): bool
    {
        $testUserIds = $testVoters->pluck('id')->all();

        return $battle->votes()
            ->whereNotNull('submitted_at')
            ->whereNotIn('user_id', $testUserIds)
            ->exists();
    }

    /**
     * @param  Collection<int, DjBattleEntry>  $entries
     */
    private function createSimulatedVote(DjBattle $battle, Collection $entries, User $voter): void
    {
        DB::transaction(function () use ($battle, $entries, $voter): void {
            $existingVote = DjBattleVote::query()
                ->where('battle_id', $battle->id)
                ->where('user_id', $voter->id)
                ->lockForUpdate()
                ->exists();

            if ($existingVote) {
                return;
            }

            $challengerScores = $this->randomScorecard();
            $opponentScores = $this->randomScorecard();
            $challengerTotal = array_sum($challengerScores);
            $opponentTotal = array_sum($opponentScores);
            $isDraw = $challengerTotal === $opponentTotal;
            $winnerProfileId = $isDraw
                ? null
                : ($challengerTotal > $opponentTotal ? $battle->challenger_dj_profile_id : $battle->opponent_dj_profile_id);

            $vote = DjBattleVote::query()->create([
                'battle_id' => $battle->id,
                'user_id' => $voter->id,
                'prediction_dj_profile_id' => $winnerProfileId,
                'vote_weight' => 1,
                'reward_eligible' => true,
                'watched_challenger_at' => now(),
                'watched_opponent_at' => now(),
                'submitted_at' => now(),
                'metadata' => [
                    'watch_order' => [$battle->challenger_dj_profile_id, $battle->opponent_dj_profile_id],
                    'score_totals' => [
                        $battle->challenger_dj_profile_id => $challengerTotal,
                        $battle->opponent_dj_profile_id => $opponentTotal,
                    ],
                    'winner_profile_id' => $winnerProfileId,
                    'is_draw' => $isDraw,
                    'score_version' => 'fan-vote-v1',
                    'simulated' => true,
                    'source' => 'battle:simulate-votes',
                ],
            ]);

            $this->createScore($vote, $battle, $entries->get($battle->challenger_dj_profile_id), $challengerScores);
            $this->createScore($vote, $battle, $entries->get($battle->opponent_dj_profile_id), $opponentScores);
        });
    }

    /**
     * @return array<string, int>
     */
    private function randomScorecard(): array
    {
        $scores = [];

        foreach (DjBattleService::VOTE_SCORE_CATEGORIES as $category) {
            $scores[$category] = random_int(1, 10);
        }

        return $scores;
    }

    /**
     * @param  array<string, int>  $categoryScores
     */
    private function createScore(DjBattleVote $vote, DjBattle $battle, DjBattleEntry $entry, array $categoryScores): void
    {
        $attributes = [
            'battle_id' => $battle->id,
            'entry_id' => $entry->id,
            'dj_profile_id' => $entry->dj_profile_id,
            'total_score' => array_sum($categoryScores),
            'metadata' => [
                'category_scores' => $categoryScores,
                'simulated' => true,
            ],
        ];

        foreach (self::SCORE_COLUMNS as $category => $column) {
            $attributes[$column] = $categoryScores[$category];
        }

        $vote->scores()->create($attributes);
    }

    private function runWinnerCalculation(DjBattle $battle, DjBattleService $battles): DjBattle
    {
        if (
            $battle->status === DjBattle::STATUS_VOTING
            && $battle->voting_ends_at
            && $battle->voting_ends_at->isPast()
        ) {
            return $battles->completeExpiredVoting($battle);
        }

        return $battles->recalculateVotingResult($battle);
    }
}
