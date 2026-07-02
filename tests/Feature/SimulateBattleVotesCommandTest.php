<?php

namespace Tests\Feature;

use App\Models\DjBattle;
use App\Models\DjBattleEntry;
use App\Models\DjProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulateBattleVotesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_simulates_votes_and_skips_duplicates(): void
    {
        $battle = $this->votingBattle();

        $this->artisan('battle:simulate-votes', [
            '--battle' => $battle->uuid,
            '--votes' => 20,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('dj_battle_votes', 20);
        $this->assertDatabaseCount('dj_battle_vote_scores', 40);
        $this->assertDatabaseHas('dj_battle_results', [
            'battle_id' => $battle->id,
            'total_votes' => 20,
            'calculation_version' => 'fan-vote-v1',
        ]);

        $this->artisan('battle:simulate-votes', [
            '--challenger' => 'Silconone',
            '--opponent' => 'DJ Chill Will',
            '--votes' => 20,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('dj_battle_votes', 20);
        $this->assertDatabaseCount('dj_battle_vote_scores', 40);
    }

    private function votingBattle(): DjBattle
    {
        $challenger = User::factory()->create(['name' => 'Silconone Account']);
        $opponent = User::factory()->create(['name' => 'DJ Chill Will Account']);
        $challengerProfile = $this->battleReadyProfile($challenger, 'Silconone', 'silconone');
        $opponentProfile = $this->battleReadyProfile($opponent, 'DJ Chill Will', 'dj-chill-will');

        $battle = DjBattle::query()->create([
            'challenger_dj_profile_id' => $challengerProfile->id,
            'opponent_dj_profile_id' => $opponentProfile->id,
            'created_by_user_id' => $challenger->id,
            'battle_type' => 'open_format',
            'status' => DjBattle::STATUS_VOTING,
            'title' => 'Silconone vs DJ Chill Will',
            'duration_seconds' => 180,
            'voting_duration_hours' => 24,
            'minimum_votes' => 1,
            'stake_amount' => 0,
            'currency' => 'TOKENS',
            'accepted_at' => now()->subDays(2),
            'recording_started_at' => now()->subDay(),
            'recording_ends_at' => now()->subHours(12),
            'voting_started_at' => now()->subHour(),
            'voting_ends_at' => now()->addDay(),
        ]);

        foreach ([$challengerProfile, $opponentProfile] as $profile) {
            DjBattleEntry::query()->create([
                'battle_id' => $battle->id,
                'dj_profile_id' => $profile->id,
                'user_id' => $profile->user_id,
                'status' => DjBattleEntry::STATUS_SUBMITTED,
                'title' => "{$profile->dj_name} Entry",
                'duration_seconds' => 120,
                'submitted_at' => now()->subMinutes(30),
            ]);
        }

        return $battle;
    }

    private function battleReadyProfile(User $user, string $name, string $handle): DjProfile
    {
        return DjProfile::query()->create([
            'user_id' => $user->id,
            'dj_name' => $name,
            'handle' => $handle,
            'dj_type' => 'battle_dj',
            'battle_enabled' => true,
            'profile_status' => 'active',
            'visibility' => 'public',
            'published_at' => now(),
        ]);
    }
}
