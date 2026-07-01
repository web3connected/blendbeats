<?php

namespace App\Services\DjBattles;

use App\Models\BattleEscrow;
use App\Models\DjBattle;
use App\Models\DjBattleEntry;
use App\Models\DjProfile;
use App\Models\DjBattleVote;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\BattleEventNotification;
use App\Services\MediaManagerService;
use App\Services\WalletService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DjBattleService
{
    private const RESPONSE_WINDOW_HOURS = 24;

    private const READY_WINDOW_HOURS = 24;

    private const RECORDING_WINDOW_HOURS = 24;

    private const STANDARD_RULES = "Both DJs receive the same AI-generated sample pack.\nAll required samples must be used.\nMaximum recording length: 3 minutes.\nRecording takes place inside the BlendBeat recorder.\nFan voting determines the winner.";

    private const SAMPLE_PACK_TESTING_BYPASS_REASON = 'AI sample generation is not ready for battle testing.';

    public const VOTE_SCORE_CATEGORIES = [
        'sample_integration',
        'scratching_ability',
        'mixing_ability',
        'blending',
        'creativity',
        'technical_execution',
        'music_selection',
        'battle_composition',
        'entertainment_value',
        'overall_performance',
    ];

    private const STARTED_ACTIVE_STATUSES = [
        DjBattle::STATUS_RECORDING,
        DjBattle::STATUS_VOTING,
        DjBattle::STATUS_DISPUTED,
    ];

    public function __construct(
        private readonly WalletService $wallets,
        private readonly MediaManagerService $mediaManager,
    ) {}

    /**
     * @param  array{
     *     battle_type: string,
     *     title: string,
     *     theme?: string|null,
     *     description?: string|null,
     *     rules?: string|null,
     *     duration_seconds?: int|null,
     *     voting_duration_hours?: int|null,
     *     minimum_votes?: int|null,
     *     stake_amount?: int|null,
     *     challenge_message?: string|null
     * }  $attributes
     */
    public function createChallenge(User $challengerUser, DjProfile $opponent, array $attributes): DjBattle
    {
        $challenger = $this->profileForUser($challengerUser);
        $this->assertBattleReadyProfile($opponent, 'opponent_dj_profile_id');

        if ($challenger->is($opponent)) {
            throw ValidationException::withMessages([
                'opponent_dj_profile_id' => ['Choose another battle-ready DJ.'],
            ]);
        }

        if ($this->activeBattleExistsBetween($challenger, $opponent)) {
            throw ValidationException::withMessages([
                'opponent_dj_profile_id' => ['There is already an active battle between these DJs.'],
            ]);
        }

        $stakeAmount = (int) ($attributes['stake_amount'] ?? 0);

        return DB::transaction(function () use ($challengerUser, $challenger, $opponent, $attributes, $stakeAmount): DjBattle {
            $responseDueAt = now()->addHours(self::RESPONSE_WINDOW_HOURS);

            $battle = DjBattle::query()->create([
                'challenger_dj_profile_id' => $challenger->id,
                'opponent_dj_profile_id' => $opponent->id,
                'created_by_user_id' => $challengerUser->id,
                'battle_type' => $attributes['battle_type'],
                'status' => DjBattle::STATUS_PENDING,
                'title' => $attributes['title'],
                'theme' => $attributes['theme'] ?? null,
                'description' => $attributes['description'] ?? null,
                'rules' => $attributes['rules'] ?? self::STANDARD_RULES,
                'duration_seconds' => (int) ($attributes['duration_seconds'] ?? 180),
                'voting_duration_hours' => (int) ($attributes['voting_duration_hours'] ?? 24),
                'minimum_votes' => (int) ($attributes['minimum_votes'] ?? 1),
                'stake_amount' => $stakeAmount,
                'currency' => 'TOKENS',
                'challenge_message' => $attributes['challenge_message'] ?? null,
                'response_due_at' => $responseDueAt,
                'expires_at' => $responseDueAt,
            ]);

            $this->recordEvent($battle, $challengerUser, 'challenge_created', null, DjBattle::STATUS_PENDING, [
                'response_due_at' => $responseDueAt->toISOString(),
                'voting_duration_hours' => (int) $battle->voting_duration_hours,
            ]);
            $this->notify($opponent->user, $battle, 'challenge_received');

            return $this->battleWithRelations($battle);
        });
    }

    public function accept(User $actor, DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->pauseExpiredChallengeIfNeeded($battle);
            $this->authorizeOpponent($actor, $battle);
            $this->assertStatus($battle, [DjBattle::STATUS_PENDING], 'This battle cannot be accepted.');

            $fromStatus = $battle->status;

            $battle->forceFill([
                'status' => DjBattle::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'ready_due_at' => now()->addHours(self::READY_WINDOW_HOURS),
            ])->save();

            $this->ensureBattleEscrow($battle);
            $this->ensureEntryPlaceholders($battle);
            $this->recordEvent($battle, $actor, 'challenge_accepted', $fromStatus, DjBattle::STATUS_ACCEPTED);
            $this->notify($battle->challenger->user, $battle, 'challenge_accepted');

            return $this->battleWithRelations($battle);
        });
    }

    public function decline(User $actor, DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->pauseExpiredChallengeIfNeeded($battle);
            $this->authorizeOpponent($actor, $battle);
            $this->assertStatus($battle, [DjBattle::STATUS_PENDING], 'This battle cannot be declined.');

            $fromStatus = $battle->status;

            $this->unlockStakeIfLocked($battle->challenger->user, $battle, 'challenger', $actor);

            $battle->forceFill([
                'status' => DjBattle::STATUS_DECLINED,
                'declined_at' => now(),
            ])->save();

            $this->recordEvent($battle, $actor, 'challenge_declined', $fromStatus, DjBattle::STATUS_DECLINED);
            $this->notify($battle->challenger->user, $battle, 'challenge_declined');

            return $this->battleWithRelations($battle);
        });
    }

    public function cancel(User $actor, DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->pauseExpiredChallengeIfNeeded($battle);
            $this->authorizeParticipant($actor, $battle);
            $this->assertStatus(
                $battle,
                [DjBattle::STATUS_PENDING, DjBattle::STATUS_PAUSED, DjBattle::STATUS_ACCEPTED, DjBattle::STATUS_RECORDING],
                'This battle cannot be cancelled.',
            );

            if (in_array($battle->status, [DjBattle::STATUS_PENDING, DjBattle::STATUS_PAUSED], true)
                && (int) $battle->challenger->user_id !== $actor->id) {
                throw new AuthorizationException('Only the challenger can cancel a pending battle.');
            }

            $fromStatus = $battle->status;
            $escrow = $this->battleEscrowFor($battle);

            $this->unlockStakeIfLocked($battle->challenger->user, $battle, 'challenger', $actor, $escrow);
            $this->unlockStakeIfLocked($battle->opponent->user, $battle, 'opponent', $actor, $escrow);

            $battle->forceFill([
                'status' => DjBattle::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ])->save();

            $this->markEscrowCancelled($escrow, $battle);
            $this->recordEvent($battle, $actor, 'battle_cancelled', $fromStatus, DjBattle::STATUS_CANCELLED);
            $this->notify($this->otherParticipantUser($actor, $battle), $battle, 'battle_cancelled');

            return $this->battleWithRelations($battle);
        });
    }

    public function extend(User $actor, DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->pauseExpiredChallengeIfNeeded($battle);
            $this->authorizeChallenger($actor, $battle);
            $this->assertStatus($battle, [DjBattle::STATUS_PAUSED], 'This challenge cannot be extended.');

            $fromStatus = $battle->status;
            $responseDueAt = now()->addHours(self::RESPONSE_WINDOW_HOURS);

            $battle->forceFill([
                'status' => DjBattle::STATUS_PENDING,
                'response_due_at' => $responseDueAt,
                'expires_at' => $responseDueAt,
            ])->save();

            $this->recordEvent($battle, $actor, 'challenge_extended', $fromStatus, DjBattle::STATUS_PENDING, [
                'response_due_at' => $responseDueAt->toISOString(),
            ]);
            $this->notify($battle->opponent->user, $battle, 'challenge_extended');

            return $this->battleWithRelations($battle);
        });
    }

    public function ready(User $actor, DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->authorizeParticipant($actor, $battle);
            $this->assertStatus($battle, [DjBattle::STATUS_ACCEPTED], 'This battle is not in the ready phase.');

            [$role, $profile] = $this->participantRoleAndProfile($actor, $battle);
            $readyColumn = "{$role}_ready_at";

            if ($battle->{$readyColumn}) {
                return $this->battleWithRelations($battle);
            }

            $this->assertReadyRequirements($actor, $profile, $battle);

            $battle->forceFill([
                $readyColumn => now(),
            ])->save();

            $this->recordEvent($battle, $actor, 'participant_ready', DjBattle::STATUS_ACCEPTED, DjBattle::STATUS_ACCEPTED, [
                'battle_role' => $role,
            ]);

            $battle->refresh();

            if ($battle->challenger_ready_at && $battle->opponent_ready_at) {
                $this->startBattle($battle, $actor);
            } else {
                $this->notify($this->otherParticipantUser($actor, $battle), $battle, 'participant_ready');
            }

            return $this->battleWithRelations($battle);
        });
    }

    public function readyOtherParticipantForTesting(User $actor, DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->authorizeParticipant($actor, $battle);
            $this->assertStatus($battle, [DjBattle::STATUS_ACCEPTED], 'This battle is not in the ready phase.');
            $this->assertTestingReadyShortcutAllowed();

            [$actorRole] = $this->participantRoleAndProfile($actor, $battle);
            $actorReadyColumn = "{$actorRole}_ready_at";

            if (! $battle->{$actorReadyColumn}) {
                throw ValidationException::withMessages([
                    'battle' => ['Submit your own ready check before simulating the other DJ.'],
                ]);
            }

            $targetRole = $actorRole === 'challenger' ? 'opponent' : 'challenger';
            $targetProfile = $targetRole === 'challenger' ? $battle->challenger : $battle->opponent;
            $targetReadyColumn = "{$targetRole}_ready_at";

            if (! $battle->{$targetReadyColumn}) {
                $this->assertReadyRequirements($targetProfile->user, $targetProfile, $battle);

                $battle->forceFill([
                    $targetReadyColumn => now(),
                ])->save();

                $this->recordEvent($battle, $actor, 'participant_ready_test_simulated', DjBattle::STATUS_ACCEPTED, DjBattle::STATUS_ACCEPTED, [
                    'battle_role' => $targetRole,
                    'simulated_user_id' => $targetProfile->user_id,
                ]);
            }

            $battle->refresh();

            if ($battle->challenger_ready_at && $battle->opponent_ready_at) {
                $this->startBattle($battle, $actor);
            }

            return $this->battleWithRelations($battle);
        });
    }

    public function bypassSamplePack(User $actor, DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->authorizeParticipant($actor, $battle);
            $this->assertStatus(
                $battle,
                [DjBattle::STATUS_ACCEPTED, DjBattle::STATUS_RECORDING],
                'Sample packs can only be bypassed once a battle is accepted.',
            );
            $this->assertSamplePackBypassAllowed();
            $this->applySamplePackBypass($battle, $actor, self::SAMPLE_PACK_TESTING_BYPASS_REASON);

            return $this->battleWithRelations($battle);
        });
    }

    /**
     * @param  array{
     *     title?: string|null,
     *     notes?: string|null,
     *     duration_seconds?: int|null,
     *     recorded_in_browser?: bool|null
     * }  $attributes
     */
    public function submitEntry(User $actor, DjBattle $battle, UploadedFile $file, array $attributes): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle, $file, $attributes): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->authorizeParticipant($actor, $battle);
            $this->assertStatus($battle, [DjBattle::STATUS_RECORDING], 'Battle entries can only be submitted during the recording phase.');
            $this->assertRecordingWindowOpen($battle);

            [$role, $profile] = $this->participantRoleAndProfile($actor, $battle);
            $entry = $this->entryForProfile($battle, $profile);

            if ($entry->status === DjBattleEntry::STATUS_SUBMITTED || $entry->submitted_at || $entry->media_file_id) {
                throw ValidationException::withMessages([
                    'entry' => ['Your battle entry has already been submitted and locked.'],
                ]);
            }

            $durationSeconds = (int) ($attributes['duration_seconds'] ?? $battle->duration_seconds);
            if ($durationSeconds > (int) $battle->duration_seconds) {
                throw ValidationException::withMessages([
                    'duration_seconds' => ["Battle recordings cannot exceed {$battle->duration_seconds} seconds."],
                ]);
            }

            $temporaryExpiresAt = now()->addDays(7);
            $mediaFile = $this->mediaManager->uploadForOwner(
                $actor,
                $file,
                'public',
                MediaManagerService::COLLECTION_DJ_VIDEO,
            );

            $mediaMetadata = [
                ...($mediaFile->metadata ?? []),
                'battle_entry' => [
                    'battle_id' => $battle->id,
                    'battle_uuid' => $battle->uuid,
                    'dj_profile_id' => $profile->id,
                    'user_id' => $actor->id,
                    'submission_id' => $entry->id,
                    'file_path' => $mediaFile->path,
                    'duration_seconds' => $durationSeconds,
                    'upload_status' => 'complete',
                    'processing_status' => 'pending',
                    'expires_at' => $temporaryExpiresAt->toISOString(),
                    'recorded_in_browser' => (bool) ($attributes['recorded_in_browser'] ?? true),
                ],
            ];

            $mediaFile->forceFill(['metadata' => $mediaMetadata])->save();

            $entry->forceFill([
                'media_file_id' => $mediaFile->id,
                'status' => DjBattleEntry::STATUS_SUBMITTED,
                'title' => ($attributes['title'] ?? null) ?: "{$profile->dj_name} Battle Entry",
                'notes' => $attributes['notes'] ?? null,
                'duration_seconds' => $durationSeconds,
                'recording_started_at' => $entry->recording_started_at ?: $battle->recording_started_at,
                'submitted_at' => now(),
                'metadata' => [
                    ...($entry->metadata ?? []),
                    'battle_role' => $role,
                    'file_path' => $mediaFile->path,
                    'file_size' => $mediaFile->size,
                    'mime_type' => $mediaFile->mime_type,
                    'duration_seconds' => $durationSeconds,
                    'upload_status' => 'complete',
                    'processing_status' => 'pending',
                    'temporary_video' => true,
                    'expires_at' => $temporaryExpiresAt->toISOString(),
                    'recorded_in_browser' => (bool) ($attributes['recorded_in_browser'] ?? true),
                ],
            ])->save();

            $this->recordEvent($battle, $actor, 'entry_submitted', $battle->status, $battle->status, [
                'battle_role' => $role,
                'entry_id' => $entry->id,
                'media_file_id' => $mediaFile->id,
                'duration_seconds' => $durationSeconds,
            ]);
            $this->openVotingIfBothEntriesSubmitted($battle, $actor);

            return $this->battleWithRelations($battle);
        });
    }

    public function duplicateSubmittedEntryForTesting(User $actor, DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->authorizeParticipant($actor, $battle);
            $this->assertStatus($battle, [DjBattle::STATUS_RECORDING], 'Test entry duplication is only available during recording.');
            $this->assertTestingEntryDuplicateAllowed();

            [, $profile] = $this->participantRoleAndProfile($actor, $battle);
            $sourceEntry = $battle->entries()
                ->with('mediaFile')
                ->where('dj_profile_id', $profile->id)
                ->where('status', DjBattleEntry::STATUS_SUBMITTED)
                ->whereNotNull('media_file_id')
                ->lockForUpdate()
                ->first();

            if (! $sourceEntry || ! $sourceEntry->mediaFile) {
                throw ValidationException::withMessages([
                    'entry' => ['Submit your own battle entry before using the test duplicate shortcut.'],
                ]);
            }

            $targetEntry = $battle->entries()
                ->with('mediaFile')
                ->where('dj_profile_id', '!=', $profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($targetEntry->status === DjBattleEntry::STATUS_SUBMITTED) {
                $this->openVotingIfBothEntriesSubmitted($battle, $actor);

                return $this->battleWithRelations($battle);
            }

            $mediaFile = $this->duplicateMediaFileForTesting($sourceEntry, $targetEntry);
            $temporaryExpiresAt = now()->addDays(7);

            $targetEntry->forceFill([
                'media_file_id' => $mediaFile->id,
                'audio_media_file_id' => $sourceEntry->audio_media_file_id,
                'status' => DjBattleEntry::STATUS_SUBMITTED,
                'title' => 'Test Duplicate: '.($sourceEntry->title ?: 'Battle Entry'),
                'notes' => 'Test Mode: Duplicate DJ 1 submission for DJ 2.',
                'duration_seconds' => $sourceEntry->duration_seconds,
                'recording_started_at' => $sourceEntry->recording_started_at,
                'submitted_at' => now(),
                'metadata' => [
                    ...($sourceEntry->metadata ?? []),
                    'test_mode_duplicate' => true,
                    'duplicated_from_entry_id' => $sourceEntry->id,
                    'duplicated_by_user_id' => $actor->id,
                    'duplicated_at' => now()->toISOString(),
                    'file_path' => $mediaFile->path,
                    'upload_status' => 'complete',
                    'processing_status' => 'pending',
                    'temporary_video' => true,
                    'expires_at' => $temporaryExpiresAt->toISOString(),
                ],
            ])->save();

            $this->recordEvent($battle, $actor, 'entry_test_duplicated', $battle->status, $battle->status, [
                'source_entry_id' => $sourceEntry->id,
                'target_entry_id' => $targetEntry->id,
                'media_file_id' => $mediaFile->id,
            ]);
            $this->openVotingIfBothEntriesSubmitted($battle, $actor);

            return $this->battleWithRelations($battle);
        });
    }

    /**
     * @param  array{
     *     watch_order?: array<int, int>,
     *     scores: array<int, array{dj_profile_id: int, scores: array<string, int>}>
     * }  $payload
     */
    public function submitFanVote(User $actor, DjBattle $battle, array $payload): DjBattle
    {
        return DB::transaction(function () use ($actor, $battle, $payload): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->assertStatus($battle, [DjBattle::STATUS_VOTING], 'This battle is not open for voting.');
            $this->assertVotingWindowOpen($battle);
            $this->assertFanCanVote($actor, $battle);

            if ($battle->votes()->where('user_id', $actor->id)->exists()) {
                throw ValidationException::withMessages([
                    'vote' => ['You have already voted in this battle.'],
                ]);
            }

            $scoresByProfile = collect($payload['scores'])
                ->keyBy(fn (array $score): int => (int) $score['dj_profile_id']);
            $requiredProfileIds = collect([$battle->challenger_dj_profile_id, $battle->opponent_dj_profile_id]);

            if ($scoresByProfile->keys()->sort()->values()->all() !== $requiredProfileIds->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'scores' => ['Submit one complete scorecard for each competing DJ.'],
                ]);
            }

            $entries = $battle->entries()
                ->whereIn('dj_profile_id', $requiredProfileIds)
                ->where('status', DjBattleEntry::STATUS_SUBMITTED)
                ->get()
                ->keyBy('dj_profile_id');

            if ($entries->count() !== 2) {
                throw ValidationException::withMessages([
                    'battle' => ['Both battle entries must be submitted before voting.'],
                ]);
            }

            $scoreTotals = [];
            $winnerProfileId = null;
            $highestScore = -1;
            $isDraw = false;

            foreach ($scoresByProfile as $profileId => $scorecard) {
                $categoryScores = $this->validatedVoteScores($scorecard['scores'] ?? []);
                $totalScore = array_sum($categoryScores);
                $scoreTotals[$profileId] = $totalScore;

                if ($totalScore > $highestScore) {
                    $highestScore = $totalScore;
                    $winnerProfileId = (int) $profileId;
                    $isDraw = false;
                } elseif ($totalScore === $highestScore) {
                    $isDraw = true;
                }
            }

            $vote = DjBattleVote::query()->create([
                'battle_id' => $battle->id,
                'user_id' => $actor->id,
                'prediction_dj_profile_id' => $isDraw ? null : $winnerProfileId,
                'vote_weight' => 1,
                'reward_eligible' => true,
                'watched_challenger_at' => now(),
                'watched_opponent_at' => now(),
                'submitted_at' => now(),
                'metadata' => [
                    'watch_order' => array_values($payload['watch_order'] ?? []),
                    'score_totals' => $scoreTotals,
                    'winner_profile_id' => $isDraw ? null : $winnerProfileId,
                    'is_draw' => $isDraw,
                    'score_version' => 'fan-vote-v1',
                ],
            ]);

            foreach ($scoresByProfile as $profileId => $scorecard) {
                $categoryScores = $this->validatedVoteScores($scorecard['scores'] ?? []);
                $entry = $entries->get((int) $profileId);

                $vote->scores()->create([
                    'battle_id' => $battle->id,
                    'entry_id' => $entry->id,
                    'dj_profile_id' => (int) $profileId,
                    'sample_integration_score' => $categoryScores['sample_integration'],
                    'scratching_score' => $categoryScores['scratching_ability'],
                    'mixing_score' => $categoryScores['mixing_ability'],
                    'blending_score' => $categoryScores['blending'],
                    'creativity_score' => $categoryScores['creativity'],
                    'technical_execution_score' => $categoryScores['technical_execution'],
                    'track_selection_score' => $categoryScores['music_selection'],
                    'battle_composition_score' => $categoryScores['battle_composition'],
                    'entertainment_value_score' => $categoryScores['entertainment_value'],
                    'overall_performance_score' => $categoryScores['overall_performance'],
                    'total_score' => array_sum($categoryScores),
                    'metadata' => [
                        'category_scores' => $categoryScores,
                    ],
                ]);
            }

            $this->updateVotingResultSnapshot($battle);
            $this->recordEvent($battle, $actor, 'fan_vote_submitted', $battle->status, $battle->status, [
                'vote_id' => $vote->id,
                'reward_eligible' => true,
                'winner_profile_id' => $isDraw ? null : $winnerProfileId,
            ]);

            return $this->battleWithRelations($battle);
        });
    }

    public function pauseExpiredChallenge(DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->pauseExpiredChallengeIfNeeded($battle);

            return $this->battleWithRelations($battle);
        });
    }

    public function completeExpiredVoting(DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($battle): DjBattle {
            $battle = $this->lockedBattle($battle);

            if ($battle->status !== DjBattle::STATUS_VOTING || ! $battle->voting_ends_at || $battle->voting_ends_at->isFuture()) {
                return $this->battleWithRelations($battle);
            }

            $fromStatus = $battle->status;
            $this->updateVotingResultSnapshot($battle);
            $battle = $battle->refresh()->load(['challenger.user', 'opponent.user', 'votes.user', 'result']);
            $winnerProfileId = $battle->result?->is_draw ? null : $battle->result?->winner_dj_profile_id;

            $this->settleBattleWallets($battle, $winnerProfileId);

            $battle->forceFill([
                'status' => DjBattle::STATUS_COMPLETED,
                'winner_dj_profile_id' => $winnerProfileId,
                'completed_at' => now(),
            ])->save();

            $this->recordEvent($battle, null, 'battle_completed', $fromStatus, DjBattle::STATUS_COMPLETED, [
                'winner_dj_profile_id' => $winnerProfileId,
                'total_votes' => (int) ($battle->result?->total_votes ?? 0),
                'is_draw' => (bool) ($battle->result?->is_draw ?? true),
            ]);
            $this->notify($battle->challenger->user, $battle, 'battle_completed');
            $this->notify($battle->opponent->user, $battle, 'battle_completed');

            return $this->battleWithRelations($battle);
        });
    }

    public function accountBattles(User $user)
    {
        $profile = $user->djProfile()->first();

        if (! $profile) {
            return collect();
        }

        $this->pauseExpiredChallengesForProfile($profile);

        return DjBattle::query()
            ->with($this->relations())
            ->where(fn ($query) => $query
                ->where('challenger_dj_profile_id', $profile->id)
                ->orWhere('opponent_dj_profile_id', $profile->id))
            ->latest()
            ->limit(100)
            ->get();
    }

    private function startBattle(DjBattle $battle, User $actor): void
    {
        $battle->loadMissing(['challenger.user', 'opponent.user']);

        $this->assertReadyRequirements($battle->challenger->user, $battle->challenger, $battle);
        $this->assertReadyRequirements($battle->opponent->user, $battle->opponent, $battle);
        $this->ensureSamplePackReadyOrBypassed($battle, $actor);
        $battle->refresh();

        $escrow = $this->ensureBattleEscrow($battle);
        $challengerLock = $this->lockStake($battle->challenger->user, $battle, 'challenger', $actor, $escrow);
        $opponentLock = $this->lockStake($battle->opponent->user, $battle, 'opponent', $actor, $escrow);

        $totalPot = (int) $battle->stake_amount * 2;
        $fanRewardPool = intdiv($totalPot, 10);
        $prizePool = $totalPot - $fanRewardPool;
        $recordingStartedAt = now();
        $recordingEndsAt = $recordingStartedAt->copy()->addHours(self::RECORDING_WINDOW_HOURS);

        $battle->forceFill([
            'status' => DjBattle::STATUS_RECORDING,
            'recording_started_at' => $recordingStartedAt,
            'recording_ends_at' => $recordingEndsAt,
            'fan_reward_pool_amount' => $fanRewardPool,
            'prize_pool_amount' => $prizePool,
        ])->save();

        $escrow->forceFill([
            'status' => BattleEscrow::STATUS_RECORDING,
            'challenger_lock_transaction_id' => $challengerLock?->id,
            'opponent_lock_transaction_id' => $opponentLock?->id,
            'fan_reward_pool_amount' => $fanRewardPool,
            'prize_pool_amount' => $prizePool,
            'locked_at' => (int) $battle->stake_amount > 0 ? ($escrow->locked_at ?? now()) : null,
            'expires_at' => $recordingEndsAt,
            'last_settlement_error' => null,
        ])->save();

        $this->recordEvent($battle, $actor, 'battle_started', DjBattle::STATUS_ACCEPTED, DjBattle::STATUS_RECORDING, [
            'total_pot' => $totalPot,
            'fan_reward_pool_amount' => $fanRewardPool,
            'prize_pool_amount' => $prizePool,
            'sample_pack_status' => $battle->sample_pack_status,
            'recording_ends_at' => $battle->recording_ends_at?->toISOString(),
        ]);
        $this->notify($battle->challenger->user, $battle, 'battle_started');
        $this->notify($battle->opponent->user, $battle, 'battle_started');
    }

    private function settleBattleWallets(DjBattle $battle, ?int $winnerProfileId): void
    {
        $escrow = $this->ensureBattleEscrow($battle);
        $escrow->forceFill([
            'status' => BattleEscrow::STATUS_SETTLING,
            'settlement_attempts' => (int) $escrow->settlement_attempts + 1,
            'last_settlement_error' => null,
        ])->save();

        $winnerReward = null;

        if ((int) $battle->stake_amount > 0) {
            if (! $winnerProfileId) {
                $this->unlockStakeIfLocked($battle->challenger->user, $battle, 'challenger', $battle->challenger->user, $escrow);
                $this->unlockStakeIfLocked($battle->opponent->user, $battle, 'opponent', $battle->opponent->user, $escrow);
            } else {
                $this->spendLockedStakeIfLocked($battle->challenger->user, $battle, 'challenger', $escrow);
                $this->spendLockedStakeIfLocked($battle->opponent->user, $battle, 'opponent', $escrow);
                $winnerReward = $this->creditWinnerReward($battle, $winnerProfileId, $escrow);
            }
        }

        $this->creditFanRewards($battle, $escrow);

        $winnerUserId = null;

        if ($winnerProfileId) {
            $winner = (int) $battle->challenger_dj_profile_id === $winnerProfileId
                ? $battle->challenger
                : $battle->opponent;
            $winnerUserId = $winner?->user_id;
        }

        $escrow->forceFill([
            'status' => $winnerProfileId ? BattleEscrow::STATUS_SETTLED : BattleEscrow::STATUS_REFUNDED,
            'winner_user_id' => $winnerUserId,
            'winner_reward_transaction_id' => $winnerReward?->id,
            'released_at' => $winnerProfileId ? now() : null,
            'refunded_at' => $winnerProfileId ? $escrow->refunded_at : now(),
            'settled_at' => now(),
            'expires_at' => null,
            'last_settlement_error' => null,
        ])->save();
    }

    private function spendLockedStakeIfLocked(User $walletOwner, DjBattle $battle, string $role, ?BattleEscrow $escrow = null): ?WalletTransaction
    {
        $stakeAmount = (int) $battle->stake_amount;

        if ($stakeAmount <= 0) {
            return null;
        }

        $wallet = $walletOwner->wallet()->first();

        if (! $wallet || ! $wallet->hasLockedBalance($stakeAmount)) {
            $message = "Battle {$role} stake lock is missing.";

            if ($escrow && ! $escrow->isDemoMode()) {
                $this->flagEscrowForAdminReview($escrow, $message);

                throw new RuntimeException($message);
            }

            if ($escrow) {
                $this->appendEscrowWarning($escrow, $message);
            }

            return null;
        }

        $lockTransaction = $escrow ? $this->lockTransactionForRole($escrow, $role) : null;

        return $this->wallets->spendLocked($walletOwner, $stakeAmount, WalletService::TYPE_BATTLE_STAKE_RELEASED, [
            'related' => $battle,
            'battle_escrow_id' => $escrow?->id,
            'reverses_transaction_id' => $lockTransaction?->id,
            'settlement_group_uuid' => $escrow?->uuid,
            'idempotency_key' => "battle:{$battle->uuid}:stake-release:{$role}",
            'description' => "Battle stake released for {$battle->title}.",
            'metadata' => [
                'battle_uuid' => $battle->uuid,
                'battle_escrow_uuid' => $escrow?->uuid,
                'battle_role' => $role,
                'battle_type' => $battle->battle_type,
            ],
        ]);
    }

    private function creditWinnerReward(DjBattle $battle, int $winnerProfileId, ?BattleEscrow $escrow = null): ?WalletTransaction
    {
        if (! (bool) config('wallet.allow_winner_payout_simulation', true) || (int) $battle->prize_pool_amount <= 0) {
            return null;
        }

        $winner = (int) $battle->challenger_dj_profile_id === $winnerProfileId
            ? $battle->challenger
            : $battle->opponent;

        return $this->wallets->credit($winner->user, (int) $battle->prize_pool_amount, WalletService::TYPE_BATTLE_WINNER_REWARD, [
            'related' => $battle,
            'battle_escrow_id' => $escrow?->id,
            'settlement_group_uuid' => $escrow?->uuid,
            'idempotency_key' => "battle:{$battle->uuid}:winner-reward",
            'description' => "Battle winner test-token reward for {$battle->title}.",
            'metadata' => [
                'battle_uuid' => $battle->uuid,
                'battle_escrow_uuid' => $escrow?->uuid,
                'winner_dj_profile_id' => $winnerProfileId,
                'prize_pool_amount' => (int) $battle->prize_pool_amount,
                'demo_mode' => true,
            ],
        ]);
    }

    private function creditFanRewards(DjBattle $battle, ?BattleEscrow $escrow = null): void
    {
        if (! (bool) config('wallet.allow_fan_reward_simulation', true) || (int) $battle->fan_reward_pool_amount <= 0) {
            return;
        }

        $eligibleVotes = $battle->votes()
            ->with('user')
            ->where('reward_eligible', true)
            ->whereNotNull('submitted_at')
            ->get();

        if ($eligibleVotes->isEmpty()) {
            $this->recordEvent($battle, null, 'fan_rewards_skipped', $battle->status, $battle->status, [
                'reason' => 'no_eligible_voters',
                'fan_reward_pool_amount' => (int) $battle->fan_reward_pool_amount,
            ]);

            return;
        }

        $share = intdiv((int) $battle->fan_reward_pool_amount, $eligibleVotes->count());
        $unclaimed = (int) $battle->fan_reward_pool_amount - ($share * $eligibleVotes->count());

        if ($share <= 0) {
            $this->recordEvent($battle, null, 'fan_rewards_skipped', $battle->status, $battle->status, [
                'reason' => 'share_rounded_to_zero',
                'eligible_voter_count' => $eligibleVotes->count(),
                'fan_reward_pool_amount' => (int) $battle->fan_reward_pool_amount,
            ]);

            return;
        }

        foreach ($eligibleVotes as $vote) {
            if (! $vote->user) {
                continue;
            }

            $this->wallets->credit($vote->user, $share, WalletService::TYPE_FAN_REWARD, [
                'related' => $battle,
                'battle_escrow_id' => $escrow?->id,
                'settlement_group_uuid' => $escrow?->uuid,
                'idempotency_key' => "battle:{$battle->uuid}:fan-reward:vote:{$vote->id}",
                'description' => "Fan reward test tokens for voting in {$battle->title}.",
                'metadata' => [
                    'battle_uuid' => $battle->uuid,
                    'battle_escrow_uuid' => $escrow?->uuid,
                    'vote_id' => $vote->id,
                    'eligible_voter_count' => $eligibleVotes->count(),
                    'unclaimed_remainder' => $unclaimed,
                    'demo_mode' => true,
                ],
            ]);
        }

        $this->recordEvent($battle, null, 'fan_rewards_distributed', $battle->status, $battle->status, [
            'eligible_voter_count' => $eligibleVotes->count(),
            'reward_per_voter' => $share,
            'unclaimed_remainder' => $unclaimed,
        ]);
    }

    private function assertRecordingWindowOpen(DjBattle $battle): void
    {
        if (! $battle->recording_ends_at || $battle->recording_ends_at->isFuture()) {
            return;
        }

        throw ValidationException::withMessages([
            'entry' => ['The recording window has expired for this battle.'],
        ]);
    }

    private function assertVotingWindowOpen(DjBattle $battle): void
    {
        if (! $battle->voting_ends_at || $battle->voting_ends_at->isFuture()) {
            return;
        }

        throw ValidationException::withMessages([
            'vote' => ['The voting window has closed for this battle.'],
        ]);
    }

    private function assertFanCanVote(User $actor, DjBattle $battle): void
    {
        if (in_array($actor->id, $battle->participantUserIds(), true)) {
            throw ValidationException::withMessages([
                'vote' => ['Competing DJs cannot vote in their own battle.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $scores
     * @return array<string, int>
     */
    private function validatedVoteScores(array $scores): array
    {
        $validated = [];

        foreach (self::VOTE_SCORE_CATEGORIES as $category) {
            if (! array_key_exists($category, $scores)) {
                throw ValidationException::withMessages([
                    "scores.{$category}" => ['Every score category is required.'],
                ]);
            }

            $score = (int) $scores[$category];

            if ($score < 1 || $score > 10) {
                throw ValidationException::withMessages([
                    "scores.{$category}" => ['Scores must be between 1 and 10.'],
                ]);
            }

            $validated[$category] = $score;
        }

        return $validated;
    }

    private function updateVotingResultSnapshot(DjBattle $battle): void
    {
        $battle->loadMissing(['votes.scores']);
        $votes = $battle->votes()
            ->with('scores')
            ->whereNotNull('submitted_at')
            ->get();

        $challengerScores = [];
        $opponentScores = [];

        foreach ($votes as $vote) {
            foreach ($vote->scores as $score) {
                if ((int) $score->dj_profile_id === (int) $battle->challenger_dj_profile_id) {
                    $challengerScores[] = (float) $score->total_score;
                }

                if ((int) $score->dj_profile_id === (int) $battle->opponent_dj_profile_id) {
                    $opponentScores[] = (float) $score->total_score;
                }
            }
        }

        $challengerAverage = count($challengerScores) > 0
            ? array_sum($challengerScores) / count($challengerScores)
            : 0;
        $opponentAverage = count($opponentScores) > 0
            ? array_sum($opponentScores) / count($opponentScores)
            : 0;
        $isDraw = $votes->count() === 0 || abs($challengerAverage - $opponentAverage) < 0.001;
        $winnerProfileId = null;

        if (! $isDraw) {
            $winnerProfileId = $challengerAverage > $opponentAverage
                ? $battle->challenger_dj_profile_id
                : $battle->opponent_dj_profile_id;
        }

        $battle->result()->updateOrCreate(
            ['battle_id' => $battle->id],
            [
                'winner_dj_profile_id' => $winnerProfileId,
                'challenger_score' => $challengerAverage,
                'opponent_score' => $opponentAverage,
                'total_votes' => $votes->count(),
                'total_vote_weight' => $votes->sum('vote_weight'),
                'is_draw' => $isDraw,
                'calculation_version' => 'fan-vote-v1',
                'score_snapshot' => [
                    'challenger_scores' => $challengerScores,
                    'opponent_scores' => $opponentScores,
                    'challenger_average' => $challengerAverage,
                    'opponent_average' => $opponentAverage,
                    'winner_dj_profile_id' => $winnerProfileId,
                ],
                'calculated_at' => now(),
            ],
        );
    }

    private function assertTestingEntryDuplicateAllowed(): void
    {
        if (app()->environment(['local', 'testing']) || (bool) config('battles.test_entry_duplicate', false)) {
            return;
        }

        throw new AuthorizationException('Test entry duplication is only available outside production.');
    }

    private function assertTestingReadyShortcutAllowed(): void
    {
        if (app()->environment(['local', 'testing']) || (bool) config('battles.test_ready_shortcut', false)) {
            return;
        }

        throw new AuthorizationException('Test ready shortcuts are only available outside production.');
    }

    private function entryForProfile(DjBattle $battle, DjProfile $profile): DjBattleEntry
    {
        return DjBattleEntry::query()
            ->where('battle_id', $battle->id)
            ->where('dj_profile_id', $profile->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function duplicateMediaFileForTesting(DjBattleEntry $sourceEntry, DjBattleEntry $targetEntry): MediaFile
    {
        $sourceFile = $sourceEntry->mediaFile;

        if (! $sourceFile) {
            throw ValidationException::withMessages([
                'entry' => ['The submitted entry does not have a video file to duplicate.'],
            ]);
        }

        $sourcePath = str_replace('\\', '/', $sourceFile->path);
        $disk = Storage::disk($sourceFile->disk);

        if (! $disk->exists($sourcePath)) {
            return $sourceFile;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'webm';
        $baseName = pathinfo($sourcePath, PATHINFO_FILENAME) ?: 'battle-entry';
        $directory = trim(dirname($sourcePath), './\\');
        $copyName = $baseName.'_test_duplicate_'.$targetEntry->id.'_'.Str::lower(Str::random(6)).'.'.$extension;
        $copyPath = $directory ? "{$directory}/{$copyName}" : $copyName;

        $disk->copy($sourcePath, $copyPath);

        return MediaFile::query()->create([
            'user_id' => $targetEntry->user_id,
            'media_account_id' => null,
            'name' => $copyName,
            'original_name' => 'test-duplicate-'.($sourceFile->original_name ?? $sourceFile->name),
            'disk' => $sourceFile->disk,
            'path' => $copyPath,
            'mime_type' => $sourceFile->mime_type,
            'size' => $sourceFile->size,
            'collection' => $sourceFile->collection,
            'metadata' => [
                ...($sourceFile->metadata ?? []),
                'test_mode_duplicate' => [
                    'source_media_file_id' => $sourceFile->id,
                    'source_entry_id' => $sourceEntry->id,
                    'target_entry_id' => $targetEntry->id,
                    'duplicated_at' => now()->toISOString(),
                ],
            ],
        ]);
    }

    private function openVotingIfBothEntriesSubmitted(DjBattle $battle, User $actor): void
    {
        if ($battle->status !== DjBattle::STATUS_RECORDING) {
            return;
        }

        $missingSubmissions = $battle->entries()
            ->where('status', '!=', DjBattleEntry::STATUS_SUBMITTED)
            ->exists();

        if ($missingSubmissions) {
            return;
        }

        $fromStatus = $battle->status;
        $votingStartedAt = now();
        $votingEndsAt = $votingStartedAt->copy()->addHours((int) $battle->voting_duration_hours);

        $battle->forceFill([
            'status' => DjBattle::STATUS_VOTING,
            'voting_started_at' => $votingStartedAt,
            'voting_ends_at' => $votingEndsAt,
        ])->save();

        $escrow = $this->ensureBattleEscrow($battle);
        $escrow->forceFill([
            'status' => BattleEscrow::STATUS_VOTING,
            'expires_at' => $votingEndsAt,
        ])->save();

        $this->recordEvent($battle, $actor, 'voting_opened', $fromStatus, DjBattle::STATUS_VOTING, [
            'voting_ends_at' => $battle->voting_ends_at?->toISOString(),
        ]);
        $this->notify($battle->challenger->user, $battle, 'voting_opened');
        $this->notify($battle->opponent->user, $battle, 'voting_opened');
    }

    private function profileForUser(User $user): DjProfile
    {
        $profile = $user->djProfile()->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'dj_profile' => ['Create a DJ profile before starting a battle.'],
            ]);
        }

        return $profile;
    }

    private function assertBattleReadyProfile(?DjProfile $profile, string $field): void
    {
        if (
            ! $profile
            || ! $profile->battle_enabled
            || $profile->profile_status !== 'active'
            || $profile->visibility !== 'public'
        ) {
            throw ValidationException::withMessages([
                $field => ['This DJ profile must be public, active, and battle-ready.'],
            ]);
        }
    }

    private function activeBattleExistsBetween(DjProfile $first, DjProfile $second): bool
    {
        return DjBattle::query()
            ->whereIn('status', DjBattle::ACTIVE_STATUSES)
            ->where(function ($query) use ($first, $second): void {
                $query
                    ->where(function ($pair) use ($first, $second): void {
                        $pair
                            ->where('challenger_dj_profile_id', $first->id)
                            ->where('opponent_dj_profile_id', $second->id);
                    })
                    ->orWhere(function ($pair) use ($first, $second): void {
                        $pair
                            ->where('challenger_dj_profile_id', $second->id)
                            ->where('opponent_dj_profile_id', $first->id);
                    });
            })
            ->exists();
    }

    private function lockedBattle(DjBattle $battle): DjBattle
    {
        return DjBattle::query()
            ->with($this->relations())
            ->whereKey($battle->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function authorizeOpponent(User $actor, DjBattle $battle): void
    {
        if ((int) $battle->opponent->user_id === $actor->id) {
            return;
        }

        throw new AuthorizationException('Only the challenged DJ can perform this battle action.');
    }

    private function authorizeChallenger(User $actor, DjBattle $battle): void
    {
        if ((int) $battle->challenger->user_id === $actor->id) {
            return;
        }

        throw new AuthorizationException('Only the challenger can perform this battle action.');
    }

    private function authorizeParticipant(User $actor, DjBattle $battle): void
    {
        if (in_array($actor->id, $battle->participantUserIds(), true)) {
            return;
        }

        throw new AuthorizationException('Only battle participants can perform this battle action.');
    }

    private function assertStatus(DjBattle $battle, array $allowedStatuses, string $message): void
    {
        if (in_array($battle->status, $allowedStatuses, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'battle' => [$message],
        ]);
    }

    private function ensureSamplePackReadyOrBypassed(DjBattle $battle, User $actor): void
    {
        if (in_array($battle->sample_pack_status, [DjBattle::SAMPLE_PACK_READY, DjBattle::SAMPLE_PACK_BYPASSED], true)) {
            return;
        }

        $this->assertSamplePackBypassAllowed();
        $this->applySamplePackBypass($battle, $actor, self::SAMPLE_PACK_TESTING_BYPASS_REASON);
    }

    private function assertSamplePackBypassAllowed(): void
    {
        if ($this->canBypassSamplePack()) {
            return;
        }

        throw ValidationException::withMessages([
            'sample_pack' => ['The battle sample pack is not ready yet.'],
        ]);
    }

    private function canBypassSamplePack(): bool
    {
        return app()->environment(['local', 'testing'])
            || (bool) config('battles.sample_pack_bypass', false)
            || (bool) config('wallet.beta_token_demo_mode', true);
    }

    private function applySamplePackBypass(DjBattle $battle, User $actor, string $reason): void
    {
        if (in_array($battle->sample_pack_status, [DjBattle::SAMPLE_PACK_READY, DjBattle::SAMPLE_PACK_BYPASSED], true)) {
            return;
        }

        $metadata = [
            ...($battle->sample_pack_metadata ?? []),
            'bypass_reason' => $reason,
            'bypass_source' => 'testing',
            'bypassed_by_user_id' => $actor->id,
        ];

        $battle->forceFill([
            'sample_pack_status' => DjBattle::SAMPLE_PACK_BYPASSED,
            'sample_pack_bypassed_at' => now(),
            'sample_pack_metadata' => $metadata,
        ])->save();

        $this->recordEvent($battle, $actor, 'sample_pack_bypassed', $battle->status, $battle->status, [
            'sample_pack_status' => DjBattle::SAMPLE_PACK_BYPASSED,
            'bypass_reason' => $reason,
        ]);
    }

    /**
     * @return array{0: string, 1: DjProfile}
     */
    private function participantRoleAndProfile(User $actor, DjBattle $battle): array
    {
        if ((int) $battle->challenger->user_id === $actor->id) {
            return ['challenger', $battle->challenger];
        }

        if ((int) $battle->opponent->user_id === $actor->id) {
            return ['opponent', $battle->opponent];
        }

        throw new AuthorizationException('Only battle participants can perform this battle action.');
    }

    private function assertReadyRequirements(User $actor, DjProfile $profile, DjBattle $battle): void
    {
        $this->assertBattleReadyProfile($profile, 'dj_profile');

        if ($this->hasOtherStartedBattle($profile, $battle)) {
            throw ValidationException::withMessages([
                'battle' => ['Already in an Active Battle'],
            ]);
        }

        if ((int) $battle->stake_amount <= 0) {
            return;
        }

        if (! (bool) config('wallet.allow_battle_staking_with_test_tokens', true)) {
            throw ValidationException::withMessages([
                'stake_amount' => ['Battle staking with test tokens is disabled.'],
            ]);
        }

        $wallet = $this->wallets->walletFor($actor);

        if (! $wallet->isActive()) {
            throw ValidationException::withMessages([
                'stake_amount' => ['Wallet is not active.'],
            ]);
        }

        if (! $wallet->hasAvailableBalance((int) $battle->stake_amount)) {
            throw ValidationException::withMessages([
                'stake_amount' => ['Insufficient Tokens'],
            ]);
        }
    }

    private function hasOtherStartedBattle(DjProfile $profile, DjBattle $battle): bool
    {
        return DjBattle::query()
            ->whereKeyNot($battle->getKey())
            ->whereIn('status', self::STARTED_ACTIVE_STATUSES)
            ->where(fn ($query) => $query
                ->where('challenger_dj_profile_id', $profile->id)
                ->orWhere('opponent_dj_profile_id', $profile->id))
            ->exists();
    }

    private function ensureBattleEscrow(DjBattle $battle): BattleEscrow
    {
        $battle->loadMissing(['challenger.user', 'opponent.user']);

        $attributes = [
            'escrow_mode' => $this->escrowModeForBattle($battle),
            'currency_type' => $battle->currency ?: 'TOKENS',
            'stake_amount' => (int) $battle->stake_amount,
            'challenger_user_id' => $battle->challenger?->user_id,
            'opponent_user_id' => $battle->opponent?->user_id,
            'expires_at' => $this->escrowExpiresAtForBattle($battle),
            'metadata' => [
                'battle_uuid' => $battle->uuid,
                'battle_type' => $battle->battle_type,
                'created_from' => 'dj_battle_service',
            ],
        ];

        $escrow = BattleEscrow::query()
            ->where('battle_id', $battle->id)
            ->lockForUpdate()
            ->first();

        if (! $escrow) {
            return BattleEscrow::query()->create([
                'battle_id' => $battle->id,
                'status' => BattleEscrow::STATUS_PENDING,
                ...$attributes,
            ]);
        }

        $metadata = [
            ...($escrow->metadata ?? []),
            ...$attributes['metadata'],
        ];

        $escrow->forceFill([
            ...$attributes,
            'metadata' => $metadata,
        ])->save();

        return $escrow;
    }

    private function battleEscrowFor(DjBattle $battle): ?BattleEscrow
    {
        return BattleEscrow::query()
            ->where('battle_id', $battle->id)
            ->lockForUpdate()
            ->first();
    }

    private function escrowModeForBattle(DjBattle $battle): string
    {
        if ((bool) config('wallet.beta_token_demo_mode', true)) {
            return BattleEscrow::MODE_DEMO;
        }

        return strtoupper((string) $battle->currency) === 'TOKENS'
            ? BattleEscrow::MODE_TOKEN
            : BattleEscrow::MODE_REAL_MONEY;
    }

    private function escrowExpiresAtForBattle(DjBattle $battle): mixed
    {
        return match ($battle->status) {
            DjBattle::STATUS_ACCEPTED => $battle->ready_due_at,
            DjBattle::STATUS_RECORDING => $battle->recording_ends_at,
            DjBattle::STATUS_VOTING => $battle->voting_ends_at,
            default => $battle->expires_at,
        };
    }

    private function lockTransactionForRole(BattleEscrow $escrow, string $role): ?WalletTransaction
    {
        $column = $role === 'challenger'
            ? 'challenger_lock_transaction_id'
            : 'opponent_lock_transaction_id';
        $transactionId = $escrow->{$column};

        if (! $transactionId) {
            return null;
        }

        return WalletTransaction::query()->find($transactionId);
    }

    private function markEscrowCancelled(?BattleEscrow $escrow, DjBattle $battle): void
    {
        if (! $escrow) {
            return;
        }

        $hadLockedStake = (bool) ($escrow->challenger_lock_transaction_id || $escrow->opponent_lock_transaction_id);

        $escrow->forceFill([
            'status' => BattleEscrow::STATUS_CANCELLED,
            'cancelled_at' => $battle->cancelled_at ?? now(),
            'refunded_at' => $hadLockedStake ? ($escrow->refunded_at ?? now()) : $escrow->refunded_at,
            'expires_at' => null,
        ])->save();
    }

    private function flagEscrowForAdminReview(BattleEscrow $escrow, string $message): void
    {
        $escrow->forceFill([
            'status' => BattleEscrow::STATUS_DISPUTED,
            'requires_admin_review' => true,
            'last_settlement_error' => $message,
            'disputed_at' => now(),
        ])->save();
    }

    private function appendEscrowWarning(BattleEscrow $escrow, string $message): void
    {
        $metadata = $escrow->metadata ?? [];
        $warnings = $metadata['warnings'] ?? [];
        $warnings[] = [
            'message' => $message,
            'recorded_at' => now()->toISOString(),
        ];

        $escrow->forceFill([
            'metadata' => [
                ...$metadata,
                'warnings' => $warnings,
            ],
        ])->save();
    }

    private function lockStake(User $walletOwner, DjBattle $battle, string $role, User $actor, ?BattleEscrow $escrow = null): ?WalletTransaction
    {
        if ((int) $battle->stake_amount <= 0) {
            return null;
        }

        try {
            return $this->wallets->lock($walletOwner, (int) $battle->stake_amount, WalletService::TYPE_BATTLE_STAKE_LOCKED, [
                'related' => $battle,
                'battle_escrow_id' => $escrow?->id,
                'settlement_group_uuid' => $escrow?->uuid,
                'idempotency_key' => "battle:{$battle->uuid}:stake-lock:{$role}",
                'description' => "Battle stake locked for {$battle->title}.",
                'created_by_user_id' => $actor->id,
                'metadata' => [
                    'battle_uuid' => $battle->uuid,
                    'battle_escrow_uuid' => $escrow?->uuid,
                    'battle_role' => $role,
                    'battle_type' => $battle->battle_type,
                ],
            ]);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'stake_amount' => [$exception->getMessage()],
            ]);
        }
    }

    private function unlockStake(User $walletOwner, DjBattle $battle, string $role, User $actor, ?BattleEscrow $escrow = null): ?WalletTransaction
    {
        if ((int) $battle->stake_amount <= 0) {
            return null;
        }

        try {
            $lockTransaction = $escrow ? $this->lockTransactionForRole($escrow, $role) : null;

            return $this->wallets->unlock($walletOwner, (int) $battle->stake_amount, WalletService::TYPE_BATTLE_REFUND, [
                'related' => $battle,
                'battle_escrow_id' => $escrow?->id,
                'reverses_transaction_id' => $lockTransaction?->id,
                'settlement_group_uuid' => $escrow?->uuid,
                'idempotency_key' => "battle:{$battle->uuid}:stake-refund:{$role}",
                'description' => "Battle stake returned for {$battle->title}.",
                'created_by_user_id' => $actor->id,
                'metadata' => [
                    'battle_uuid' => $battle->uuid,
                    'battle_escrow_uuid' => $escrow?->uuid,
                    'battle_role' => $role,
                    'battle_type' => $battle->battle_type,
                ],
            ]);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'stake_amount' => [$exception->getMessage()],
            ]);
        }
    }

    private function unlockStakeIfLocked(User $walletOwner, DjBattle $battle, string $role, User $actor, ?BattleEscrow $escrow = null): ?WalletTransaction
    {
        if ((int) $battle->stake_amount <= 0) {
            return null;
        }

        $wallet = $walletOwner->wallet()->first();

        if (! $wallet || ! $wallet->hasLockedBalance((int) $battle->stake_amount)) {
            return null;
        }

        return $this->unlockStake($walletOwner, $battle, $role, $actor, $escrow);
    }

    private function ensureEntryPlaceholders(DjBattle $battle): void
    {
        $battle->loadMissing(['challenger', 'opponent']);

        foreach ([$battle->challenger, $battle->opponent] as $profile) {
            DjBattleEntry::query()->firstOrCreate(
                [
                    'battle_id' => $battle->id,
                    'dj_profile_id' => $profile->id,
                ],
                [
                    'user_id' => $profile->user_id,
                    'status' => DjBattleEntry::STATUS_NOT_STARTED,
                ],
            );
        }
    }

    private function recordEvent(
        DjBattle $battle,
        ?User $actor,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        array $metadata = [],
    ): void {
        $battle->events()->create([
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => [
                'stake_amount' => (int) $battle->stake_amount,
                ...$metadata,
            ],
        ]);
    }

    private function pauseExpiredChallengesForProfile(DjProfile $profile): void
    {
        DjBattle::query()
            ->where('status', DjBattle::STATUS_PENDING)
            ->whereNotNull('response_due_at')
            ->where('response_due_at', '<=', now())
            ->where(fn ($query) => $query
                ->where('challenger_dj_profile_id', $profile->id)
                ->orWhere('opponent_dj_profile_id', $profile->id))
            ->get()
            ->each(fn (DjBattle $battle): DjBattle => $this->pauseExpiredChallenge($battle));
    }

    private function pauseExpiredChallengeIfNeeded(DjBattle $battle): void
    {
        $responseDueAt = $battle->response_due_at ?? $battle->expires_at;

        if ($battle->status !== DjBattle::STATUS_PENDING || ! $responseDueAt || $responseDueAt->isFuture()) {
            return;
        }

        $fromStatus = $battle->status;

        $battle->forceFill([
            'status' => DjBattle::STATUS_PAUSED,
        ])->save();

        $this->recordEvent($battle, null, 'challenge_paused', $fromStatus, DjBattle::STATUS_PAUSED, [
            'reason' => 'response_timeout',
            'response_due_at' => $responseDueAt->toISOString(),
        ]);
        $this->notify($battle->challenger->user, $battle, 'challenge_paused');
    }

    private function notify(?User $user, DjBattle $battle, string $event): void
    {
        if (! $user || ! Schema::hasTable('notifications')) {
            return;
        }

        $user->notify(new BattleEventNotification($this->battleWithRelations($battle), $event));
    }

    private function otherParticipantUser(User $actor, DjBattle $battle): ?User
    {
        return (int) $battle->challenger->user_id === $actor->id
            ? $battle->opponent->user
            : $battle->challenger->user;
    }

    private function battleWithRelations(DjBattle $battle): DjBattle
    {
        return $battle->refresh()->load($this->relations());
    }

    private function relations(): array
    {
        return [
            'challenger.user',
            'opponent.user',
            'winner',
            'entries.mediaFile',
            'entries.djProfile',
            'result',
            'battleEscrow',
        ];
    }
}
