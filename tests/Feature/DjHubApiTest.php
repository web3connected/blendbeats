<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserGamificationStat;
use Database\Seeders\GamificationActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DjHubApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dj_hub_endpoint_returns_empty_payload_until_profile_tables_exist(): void
    {
        $this->getJson('/api/dj-hub/djs')
            ->assertOk()
            ->assertJsonPath('djs', [])
            ->assertJsonPath('featured_djs', [])
            ->assertJsonPath('filters.genres', [])
            ->assertJsonPath('filters.dj_types', []);
    }

    public function test_dj_hub_featured_mix_uses_public_media_url_not_protected_stream(): void
    {
        $user = User::factory()->create(['name' => 'DJ Playable']);

        $profileId = DB::table('dj_profiles')->insertGetId([
            'user_id' => $user->id,
            'dj_name' => 'DJ Playable',
            'handle' => 'dj-playable',
            'profile_status' => 'active',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('media_files')->insert([
            'user_id' => $user->id,
            'name' => 'playable.mp3',
            'original_name' => 'playable.mp3',
            'disk' => 'public',
            'path' => 'media/accounts/user-dj-playable/dj_media/playable.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
            'collection' => 'dj_media',
            'metadata' => json_encode([
                'portfolio' => [
                    'title' => 'Playable Mix',
                    'visibility' => 'public',
                    'media_kind' => 'mix',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dj_featured_status')->insert([
            'dj_profile_id' => $profileId,
            'featured_type' => 'Paid Spotlight',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/dj-hub/djs')
            ->assertOk()
            ->assertJsonPath('djs.0.featured_mix.title', 'Playable Mix')
            ->assertJsonPath('djs.0.featured_mix.url', '/storage/media/accounts/user-dj-playable/dj_media/playable.mp3')
            ->assertJsonPath('featured_djs.0.featured_mix.url', '/storage/media/accounts/user-dj-playable/dj_media/playable.mp3');
    }

    public function test_public_dj_profile_exposes_dj_gamification_summary(): void
    {
        $user = User::factory()->create(['name' => 'DJ Progress']);

        DB::table('dj_profiles')->insert([
            'user_id' => $user->id,
            'dj_name' => 'DJ Progress',
            'handle' => 'dj-progress',
            'profile_status' => 'active',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/dj-hub/djs/dj-progress')
            ->assertOk()
            ->assertJsonPath('dj.gamification.dj_level', 1)
            ->assertJsonPath('dj.gamification.dj_xp', 0)
            ->assertJsonPath('dj.gamification.dj_rank', 'New DJ')
            ->assertJsonPath('dj.gamification.badges', [])
            ->assertJsonPath('dj.ranking.global_rank', null)
            ->assertJsonPath('dj.battle_stats.battles', 0)
            ->assertJsonPath('dj.battle_stats.win_rate', 0);

        UserGamificationStat::query()->create([
            'user_id' => $user->id,
            'dj_xp' => 125,
            'fan_xp' => 40,
            'total_xp' => 165,
            'dj_level' => 2,
            'fan_level' => 1,
            'total_level' => 2,
            'dj_rank' => 'Rising DJ',
            'fan_rank' => 'Supporter',
            'last_activity_at' => now(),
        ]);

        $djBadge = Badge::query()->create([
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

        $fanBadge = Badge::query()->create([
            'badge_key' => 'super_fan',
            'name' => 'Super Fan',
            'description' => 'Reached a fan engagement milestone.',
            'role_context' => 'fan',
            'icon' => 'badges/super-fan.svg',
            'rarity' => 'rare',
            'unlock_action_key' => 'dj_followed',
            'unlock_threshold' => 10,
            'is_active' => true,
        ]);

        UserBadge::query()->create([
            'user_id' => $user->id,
            'badge_id' => $djBadge->id,
            'unlocked_at' => now(),
        ]);

        UserBadge::query()->create([
            'user_id' => $user->id,
            'badge_id' => $fanBadge->id,
            'unlocked_at' => now(),
        ]);

        $this->getJson('/api/dj-hub/djs/dj-progress')
            ->assertOk()
            ->assertJsonPath('dj.gamification.dj_level', 2)
            ->assertJsonPath('dj.gamification.dj_xp', 125)
            ->assertJsonPath('dj.gamification.dj_rank', 'Rising DJ')
            ->assertJsonCount(1, 'dj.gamification.badges')
            ->assertJsonPath('dj.gamification.badges.0.badge_key', 'first_portfolio_upload')
            ->assertJsonPath('dj.gamification.badges.0.name', 'First Upload')
            ->assertJsonPath('dj.gamification.badges.0.icon', 'badges/first-upload.svg')
            ->assertJsonPath('dj.gamification.badges.0.rarity', 'common')
            ->assertJsonMissing(['badge_key' => 'super_fan']);
    }

    public function test_following_dj_awards_fan_xp_once(): void
    {
        Notification::fake();
        $this->seed(GamificationActionSeeder::class);

        $dj = User::factory()->create(['name' => 'DJ Followable']);
        $fan = User::factory()->create(['name' => 'Fan Follower']);

        $profileId = DB::table('dj_profiles')->insertGetId([
            'user_id' => $dj->id,
            'dj_name' => 'DJ Followable',
            'handle' => 'dj-followable',
            'profile_status' => 'active',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($fan)
            ->postJson('/api/dj-hub/djs/dj-followable/follow')
            ->assertOk()
            ->assertJsonPath('is_following', true)
            ->assertJsonPath('followers_count', 1);

        $this->actingAs($fan)
            ->postJson('/api/dj-hub/djs/dj-followable/follow')
            ->assertOk()
            ->assertJsonPath('is_following', true)
            ->assertJsonPath('followers_count', 1);

        $this->assertDatabaseHas('gamification_events', [
            'user_id' => $fan->id,
            'action_key' => 'dj_followed',
            'role_context' => 'fan',
            'xp_awarded' => 15,
            'target_type' => 'dj_profile',
            'target_id' => $profileId,
        ]);

        $this->assertSame(1, DB::table('gamification_events')
            ->where('user_id', $fan->id)
            ->where('action_key', 'dj_followed')
            ->where('target_type', 'dj_profile')
            ->where('target_id', $profileId)
            ->count());

        $stats = UserGamificationStat::query()->where('user_id', $fan->id)->firstOrFail();

        $this->assertSame(0, (int) $stats->dj_xp);
        $this->assertSame(15, (int) $stats->fan_xp);
        $this->assertSame(15, (int) $stats->total_xp);
        $this->assertSame(1, (int) $stats->fan_level);
        $this->assertNotNull($stats->last_activity_at);
    }
}
