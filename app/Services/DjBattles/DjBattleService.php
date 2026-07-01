<?php

namespace App\Services\DjBattles;

use App\Models\DjBattle;
use App\Models\DjBattleEntry;
use App\Models\DjProfile;
use App\Models\MediaFile;
use App\Models\User;
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

            $this->unlockStakeIfLocked($battle->challenger->user, $battle, 'challenger', $actor);
            $this->unlockStakeIfLocked($battle->opponent->user, $battle, 'opponent', $actor);

            $battle->forceFill([
                'status' => DjBattle::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ])->save();

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

    public function pauseExpiredChallenge(DjBattle $battle): DjBattle
    {
        return DB::transaction(function () use ($battle): DjBattle {
            $battle = $this->lockedBattle($battle);
            $this->pauseExpiredChallengeIfNeeded($battle);

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

        $this->lockStake($battle->challenger->user, $battle, 'challenger', $actor);
        $this->lockStake($battle->opponent->user, $battle, 'opponent', $actor);

        $totalPot = (int) $battle->stake_amount * 2;
        $fanRewardPool = intdiv($totalPot, 10);
        $prizePool = $totalPot - $fanRewardPool;
        $recordingStartedAt = now();

        $battle->forceFill([
            'status' => DjBattle::STATUS_RECORDING,
            'recording_started_at' => $recordingStartedAt,
            'recording_ends_at' => $recordingStartedAt->copy()->addHours(self::RECORDING_WINDOW_HOURS),
            'fan_reward_pool_amount' => $fanRewardPool,
            'prize_pool_amount' => $prizePool,
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

    private function assertRecordingWindowOpen(DjBattle $battle): void
    {
        if (! $battle->recording_ends_at || $battle->recording_ends_at->isFuture()) {
            return;
        }

        throw ValidationException::withMessages([
            'entry' => ['The recording window has expired for this battle.'],
        ]);
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

        $battle->forceFill([
            'status' => DjBattle::STATUS_VOTING,
            'voting_started_at' => $votingStartedAt,
            'voting_ends_at' => $votingStartedAt->copy()->addHours((int) $battle->voting_duration_hours),
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

    private function lockStake(User $walletOwner, DjBattle $battle, string $role, User $actor): void
    {
        if ((int) $battle->stake_amount <= 0) {
            return;
        }

        try {
            $this->wallets->lock($walletOwner, (int) $battle->stake_amount, 'battle_entry_lock', [
                'related' => $battle,
                'description' => "Battle stake locked for {$battle->title}.",
                'created_by_user_id' => $actor->id,
                'metadata' => [
                    'battle_uuid' => $battle->uuid,
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

    private function unlockStake(User $walletOwner, DjBattle $battle, string $role, User $actor): void
    {
        if ((int) $battle->stake_amount <= 0) {
            return;
        }

        try {
            $this->wallets->unlock($walletOwner, (int) $battle->stake_amount, 'battle_entry_refund', [
                'related' => $battle,
                'description' => "Battle stake returned for {$battle->title}.",
                'created_by_user_id' => $actor->id,
                'metadata' => [
                    'battle_uuid' => $battle->uuid,
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

    private function unlockStakeIfLocked(User $walletOwner, DjBattle $battle, string $role, User $actor): void
    {
        if ((int) $battle->stake_amount <= 0) {
            return;
        }

        $wallet = $walletOwner->wallet()->first();

        if (! $wallet || ! $wallet->hasLockedBalance((int) $battle->stake_amount)) {
            return;
        }

        $this->unlockStake($walletOwner, $battle, $role, $actor);
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
        ];
    }
}
