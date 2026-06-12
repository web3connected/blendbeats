<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DjProfileSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_dj_profile_from_frontend_flow(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/dj/profile', [
                'dj_name' => 'DJ Frontend',
                'handle' => 'dj-frontend',
                'profile_headline' => 'Built from the React setup flow',
                'bio' => 'This profile was created from the DJ account setup flow.',
                'banner_url' => 'https://example.com/banner.jpg',
                'primary_genre' => 'Hip-Hop',
                'secondary_genres' => ['House'],
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
            ->assertJsonPath('dj_profile.dj_name', 'DJ Frontend')
            ->assertJsonPath('dj_profile.primary_genre', 'Hip-Hop')
            ->assertJsonPath('dj_profile.secondary_genres.0', 'House');

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.dj_profile.dj_name', 'DJ Frontend')
            ->assertJsonPath('user.dj_profile.handle', 'dj-frontend');

        $this->assertDatabaseHas('dj_profiles', [
            'user_id' => $user->id,
            'handle' => 'dj-frontend',
            'profile_status' => 'active',
        ]);
    }
}
