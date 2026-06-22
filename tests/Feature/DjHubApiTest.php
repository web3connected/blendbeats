<?php

namespace Tests\Feature;

use App\Models\User;
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
            ->assertJsonPath('dj.gamification.badges', []);

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

        $this->getJson('/api/dj-hub/djs/dj-progress')
            ->assertOk()
            ->assertJsonPath('dj.gamification.dj_level', 2)
            ->assertJsonPath('dj.gamification.dj_xp', 125)
            ->assertJsonPath('dj.gamification.dj_rank', 'Rising DJ')
            ->assertJsonPath('dj.gamification.badges', []);
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
