<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
