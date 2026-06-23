<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\GamificationEvent;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserGamificationStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountGamificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_gamification_returns_default_summary_without_stats(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/account/gamification')
            ->assertOk()
            ->assertExactJson([
                'dj_xp' => 0,
                'fan_xp' => 0,
                'total_xp' => 0,
                'dj_level' => 1,
                'fan_level' => 1,
                'total_level' => 1,
                'dj_rank' => null,
                'fan_rank' => null,
                'last_activity_at' => null,
                'badges' => [],
            ]);
    }

    public function test_account_gamification_returns_current_user_summary(): void
    {
        $user = User::factory()->create();
        $activityAt = now()->subMinute()->startOfSecond();

        UserGamificationStat::query()->create([
            'user_id' => $user->id,
            'dj_xp' => 25,
            'fan_xp' => 40,
            'total_xp' => 65,
            'dj_level' => 1,
            'fan_level' => 1,
            'total_level' => 1,
            'dj_rank' => 'Bedroom DJ',
            'fan_rank' => 'Supporter',
            'last_activity_at' => $activityAt,
        ]);

        $this->actingAs($user)
            ->getJson('/api/account/gamification')
            ->assertOk()
            ->assertExactJson([
                'dj_xp' => 25,
                'fan_xp' => 40,
                'total_xp' => 65,
                'dj_level' => 1,
                'fan_level' => 1,
                'total_level' => 1,
                'dj_rank' => 'Bedroom DJ',
                'fan_rank' => 'Supporter',
                'last_activity_at' => $activityAt->toISOString(),
                'badges' => [],
            ]);
    }

    public function test_account_gamification_returns_unlocked_badges(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $unlockedAt = now()->subMinute()->startOfSecond();

        $badge = Badge::query()->create([
            'badge_key' => 'first_portfolio_upload',
            'name' => 'First Upload',
            'description' => 'Uploaded your first portfolio item.',
            'role_context' => 'dj',
            'icon' => 'badges/first-upload.svg',
            'rarity' => 'common',
            'unlock_action_key' => 'portfolio_uploaded',
            'unlock_threshold' => 1,
            'is_active' => true,
        ]);

        UserBadge::query()->create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'unlocked_at' => $unlockedAt,
        ]);

        UserBadge::query()->create([
            'user_id' => $otherUser->id,
            'badge_id' => $badge->id,
            'unlocked_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/account/gamification')
            ->assertOk()
            ->assertJsonCount(1, 'badges')
            ->assertJsonPath('badges.0.badge_key', 'first_portfolio_upload')
            ->assertJsonPath('badges.0.name', 'First Upload')
            ->assertJsonPath('badges.0.description', 'Uploaded your first portfolio item.')
            ->assertJsonPath('badges.0.icon', 'badges/first-upload.svg')
            ->assertJsonPath('badges.0.rarity', 'common')
            ->assertJsonPath('badges.0.unlocked_at', $unlockedAt->toISOString());
    }

    public function test_account_gamification_events_returns_latest_user_events(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        GamificationEvent::query()->create([
            'user_id' => $otherUser->id,
            'action_key' => 'portfolio_uploaded',
            'role_context' => 'dj',
            'xp_awarded' => 25,
            'target_type' => 'media_file',
            'target_id' => 999,
            'event_hash' => 'other-user-event',
            'metadata' => ['source_type' => 'upload'],
        ]);

        for ($index = 1; $index <= 12; $index++) {
            GamificationEvent::query()->create([
                'user_id' => $user->id,
                'action_key' => $index === 12 ? 'scratch_uploaded' : 'portfolio_uploaded',
                'role_context' => 'dj',
                'xp_awarded' => $index === 12 ? 40 : 25,
                'target_type' => 'media_file',
                'target_id' => $index,
                'event_hash' => "user-event-{$index}",
                'metadata' => ['index' => $index],
                'created_at' => now()->addSeconds($index),
                'updated_at' => now()->addSeconds($index),
            ]);
        }

        $this->actingAs($user)
            ->getJson('/api/account/gamification/events')
            ->assertOk()
            ->assertJsonCount(10)
            ->assertJsonPath('0.action_key', 'scratch_uploaded')
            ->assertJsonPath('0.xp_awarded', 40)
            ->assertJsonPath('0.role_context', 'dj')
            ->assertJsonPath('0.metadata.index', 12)
            ->assertJsonPath('9.metadata.index', 3)
            ->assertJsonMissing(['event_hash' => 'other-user-event']);
    }
}
