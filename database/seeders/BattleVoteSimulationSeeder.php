<?php

namespace Database\Seeders;

use App\Models\DjBattle;
use App\Models\DjBattleEntry;
use App\Models\DjProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BattleVoteSimulationSeeder extends Seeder
{
    /**
     * Create the live/dev fixture used by battle:simulate-votes.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $challengerUser = User::query()->updateOrCreate(
                ['email' => 'silconone.test@blendbeats.test'],
                [
                    'name' => 'Silconone',
                    'first_name' => 'Silconone',
                    'last_name' => null,
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'use_gravatar' => true,
                    'is_gravatar' => true,
                    'media_storage_tier' => 'pro',
                ],
            );

            $opponentUser = User::query()->updateOrCreate(
                ['email' => 'dj.chill.will.test@blendbeats.test'],
                [
                    'name' => 'DJ Chill Will',
                    'first_name' => 'DJ',
                    'last_name' => 'Chill Will',
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'use_gravatar' => true,
                    'is_gravatar' => true,
                    'media_storage_tier' => 'pro',
                ],
            );

            $challenger = $this->profileFor($challengerUser, 'Silconone', 'silconone', 'Atlanta', 'GA');
            $opponent = $this->profileFor($opponentUser, 'DJ Chill Will', 'dj-chill-will', 'Miami', 'FL');

            $battle = DjBattle::query()->updateOrCreate(
                ['title' => 'Silconone vs DJ Chill Will'],
                [
                    'challenger_dj_profile_id' => $challenger->id,
                    'opponent_dj_profile_id' => $opponent->id,
                    'created_by_user_id' => $challengerUser->id,
                    'battle_type' => 'open_format',
                    'status' => DjBattle::STATUS_VOTING,
                    'theme' => 'Vote simulation live test',
                    'description' => 'Live test battle for simulated fan scorecards.',
                    'rules' => 'Three-minute routine. One take.',
                    'duration_seconds' => 180,
                    'voting_duration_hours' => 24,
                    'minimum_votes' => 1,
                    'stake_amount' => 0,
                    'currency' => 'TOKENS',
                    'sample_pack_status' => DjBattle::SAMPLE_PACK_BYPASSED,
                    'sample_pack_bypassed_at' => now(),
                    'accepted_at' => now()->subDays(2),
                    'recording_started_at' => now()->subDay(),
                    'recording_ends_at' => now()->subHours(12),
                    'voting_started_at' => now()->subHour(),
                    'voting_ends_at' => now()->addDay(),
                    'completed_at' => null,
                    'winner_dj_profile_id' => null,
                ],
            );

            $this->submittedEntryFor($battle, $challenger, $challengerUser);
            $this->submittedEntryFor($battle, $opponent, $opponentUser);

            $this->command?->info("Battle vote simulation fixture ready: {$battle->title} ({$battle->uuid})");
        });
    }

    private function profileFor(User $user, string $djName, string $handle, string $city, string $state): DjProfile
    {
        return DjProfile::query()->updateOrCreate(
            ['handle' => $handle],
            [
                'user_id' => $user->id,
                'dj_name' => $djName,
                'profile_headline' => 'Battle vote simulation test DJ.',
                'bio' => 'Test DJ profile for the battle vote simulator.',
                'dj_type' => 'battle_dj',
                'city' => $city,
                'state' => $state,
                'country' => 'US',
                'booking_enabled' => true,
                'battle_enabled' => true,
                'profile_status' => 'active',
                'visibility' => 'public',
                'verification_status' => 'verified',
                'published_at' => now()->subDays(7),
                'view_count' => 0,
            ],
        );
    }

    private function submittedEntryFor(DjBattle $battle, DjProfile $profile, User $user): void
    {
        DjBattleEntry::query()->updateOrCreate(
            [
                'battle_id' => $battle->id,
                'dj_profile_id' => $profile->id,
            ],
            [
                'user_id' => $user->id,
                'status' => DjBattleEntry::STATUS_SUBMITTED,
                'title' => "{$profile->dj_name} Live Test Entry",
                'notes' => 'Simulated submitted entry for live vote testing.',
                'duration_seconds' => 120,
                'metadata' => ['source' => 'battle_vote_simulation_seeder'],
                'recording_started_at' => now()->subHours(3),
                'submitted_at' => now()->subHours(2),
            ],
        );
    }
}
