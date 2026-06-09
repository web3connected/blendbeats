<?php

namespace Tests\Feature;

use App\Models\DjFeaturedStatus;
use App\Models\DjGenre;
use App\Models\DjProfile;
use App\Models\Follower;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DjHubApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_dj_hub_lists_public_active_djs_with_directory_data(): void
    {
        $djUser = User::factory()->create([
            'name' => 'Silicon One',
            'avatar' => 'https://example.com/avatar.jpg',
            'is_gravatar' => false,
            'use_gravatar' => false,
        ]);
        $follower = User::factory()->create();
        $genre = DjGenre::create(['name' => 'House', 'slug' => 'house']);
        $profile = DjProfile::create([
            'user_id' => $djUser->id,
            'dj_name' => 'DJ SiliconOne',
            'handle' => 'siliconone',
            'profile_headline' => 'House blends and battle cuts',
            'bio' => 'Public DJ profile.',
            'dj_type' => 'open_format',
            'city' => 'Atlanta',
            'state' => 'GA',
            'country' => 'US',
            'booking_enabled' => true,
            'profile_status' => 'active',
            'visibility' => 'public',
            'published_at' => now(),
        ]);

        $profile->genres()->attach($genre->id, ['is_primary' => true, 'sort_order' => 0]);
        Follower::create(['follower_user_id' => $follower->id, 'followed_dj_id' => $profile->id]);
        DjFeaturedStatus::create(['dj_profile_id' => $profile->id, 'featured_type' => 'Featured Artist']);
        MediaFile::create([
            'user_id' => $djUser->id,
            'name' => 'Sunday Blend',
            'original_name' => 'sunday-blend.mp3',
            'disk' => 'public',
            'path' => 'media/accounts/siliconone/dj_media/sunday-blend.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 2048,
            'collection' => 'dj_media',
        ]);

        DjProfile::create([
            'user_id' => User::factory()->create()->id,
            'dj_name' => 'Private DJ',
            'handle' => 'private-dj',
            'bio' => 'Hidden profile.',
            'profile_status' => 'active',
            'visibility' => 'private',
        ]);

        $this->getJson('/api/dj-hub/djs?search=Silicon&genre=House&bookings=1')
            ->assertOk()
            ->assertJsonCount(1, 'djs')
            ->assertJsonPath('djs.0.dj_name', 'DJ SiliconOne')
            ->assertJsonPath('djs.0.primary_genre', 'House')
            ->assertJsonPath('djs.0.location', 'Atlanta, GA, US')
            ->assertJsonPath('djs.0.open_for_bookings', true)
            ->assertJsonPath('djs.0.followers_count', 1)
            ->assertJsonPath('djs.0.featured_statuses.0', 'Featured Artist')
            ->assertJsonPath('djs.0.featured_mix.title', 'Sunday Blend')
            ->assertJsonPath('filters.genres.0', 'House');
    }

    public function test_public_dj_hub_shows_profile_by_handle(): void
    {
        $user = User::factory()->create();
        $profile = DjProfile::create([
            'user_id' => $user->id,
            'dj_name' => 'DJ Endpoint Hub',
            'handle' => 'endpoint-hub',
            'profile_headline' => 'Public hub profile',
            'bio' => 'Shown by handle.',
            'profile_status' => 'active',
            'visibility' => 'public',
        ]);

        $this->getJson('/api/dj-hub/djs/'.$profile->handle)
            ->assertOk()
            ->assertJsonPath('dj.dj_name', 'DJ Endpoint Hub')
            ->assertJsonPath('dj.handle', 'endpoint-hub');
    }
}
