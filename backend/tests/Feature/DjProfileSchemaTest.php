<?php

namespace Tests\Feature;

use App\Models\DjBookingSetting;
use App\Models\DjGenre;
use App\Models\DjMedia;
use App\Models\DjProfile;
use App\Models\DjSocialLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DjProfileSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_dj_profile_with_related_setup_data(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'avatar' => 'https://example.com/account-avatar.jpg',
            'use_gravatar' => false,
        ])->save();

        $hipHop = DjGenre::create(['name' => 'Hip-Hop', 'slug' => 'hip-hop']);
        $house = DjGenre::create(['name' => 'House', 'slug' => 'house']);

        $profile = DjProfile::create([
            'user_id' => $user->id,
            'dj_name' => 'DJ Schema',
            'handle' => 'dj-schema',
            'profile_headline' => 'Battle-ready open format DJ',
            'bio' => 'Built for the five minute profile setup.',
            'dj_type' => 'open_format',
            'city' => 'Atlanta',
            'state' => 'GA',
            'country' => 'US',
            'booking_enabled' => true,
            'battle_enabled' => true,
            'profile_status' => 'draft',
            'visibility' => 'public',
        ]);

        $profile->genres()->attach($hipHop->id, ['is_primary' => true, 'sort_order' => 0]);
        $profile->genres()->attach($house->id, ['is_primary' => false, 'sort_order' => 1]);

        DjSocialLink::create([
            'dj_profile_id' => $profile->id,
            'platform' => 'instagram',
            'url' => 'https://instagram.com/djschema',
        ]);

        DjBookingSetting::create([
            'dj_profile_id' => $profile->id,
            'available_for_bookings' => true,
            'booking_email' => 'bookings@example.com',
            'show_booking_email' => true,
        ]);

        DjMedia::create([
            'dj_profile_id' => $profile->id,
            'type' => 'banner',
            'url' => 'https://example.com/banner.jpg',
            'is_primary' => true,
        ]);

        $this->assertSame('DJ Schema', $user->djProfile()->first()?->dj_name);
        $this->assertSame('https://example.com/account-avatar.jpg', $user->getAvatarUrl());
        $this->assertSame(2, $profile->genres()->count());
        $this->assertSame('bookings@example.com', $profile->bookingSetting()->first()?->booking_email);
        $this->assertSame('instagram', $profile->socialLinks()->first()?->platform);
        $this->assertSame('banner', $profile->media()->first()?->type);
    }

    public function test_authenticated_user_can_save_dj_profile_through_api(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'avatar' => 'https://example.com/account-avatar.jpg',
            'is_gravatar' => false,
            'use_gravatar' => false,
        ])->save();

        $this->actingAs($user)
            ->postJson('/api/dj/profile', [
                'dj_name' => 'DJ Endpoint',
                'handle' => 'dj-endpoint',
                'profile_headline' => 'Endpoint tested DJ profile',
                'bio' => 'This profile was created through the API.',
                'banner_url' => 'https://example.com/banner.jpg',
                'primary_genre' => 'Hip-Hop',
                'secondary_genres' => ['House', 'R&B'],
                'dj_type' => 'open_format',
                'city' => 'Atlanta',
                'state' => 'GA',
                'country' => 'US',
                'website' => 'https://example.com',
                'available_for_bookings' => true,
                'booking_email' => 'bookings@example.com',
                'visibility' => 'public',
            ])
            ->assertOk()
            ->assertJsonPath('dj_profile.dj_name', 'DJ Endpoint')
            ->assertJsonPath('dj_profile.handle', 'dj-endpoint');

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.is_gravatar', false)
            ->assertJsonPath('user.custom_avatar_url', 'https://example.com/account-avatar.jpg')
            ->assertJsonPath('user.dj_profile.dj_name', 'DJ Endpoint')
            ->assertJsonPath('user.dj_profile.profile_status', 'active');

        $this->actingAs($user)
            ->getJson('/api/dj/profile')
            ->assertOk()
            ->assertJsonPath('dj_profile.dj_name', 'DJ Endpoint')
            ->assertJsonPath('dj_profile.handle', 'dj-endpoint')
            ->assertJsonPath('dj_profile.primary_genre', 'Hip-Hop')
            ->assertJsonPath('dj_profile.secondary_genres.0', 'House')
            ->assertJsonPath('dj_profile.website', 'https://example.com')
            ->assertJsonPath('dj_profile.available_for_bookings', true)
            ->assertJsonPath('dj_profile.booking_email', 'bookings@example.com')
            ->assertJsonPath('dj_profile.banner_url', 'https://example.com/banner.jpg');

        $this->assertDatabaseHas('dj_profiles', [
            'user_id' => $user->id,
            'handle' => 'dj-endpoint',
        ]);
        $this->assertDatabaseHas('dj_booking_settings', [
            'booking_email' => 'bookings@example.com',
            'available_for_bookings' => true,
        ]);
        $this->assertDatabaseHas('dj_media', [
            'type' => 'banner',
            'url' => 'https://example.com/banner.jpg',
        ]);
    }
}
