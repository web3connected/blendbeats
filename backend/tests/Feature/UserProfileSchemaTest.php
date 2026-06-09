<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_first_and_last_name_columns(): void
    {
        $user = User::create([
            'name' => 'DJ Profile',
            'first_name' => 'DJ',
            'last_name' => 'Profile',
            'email' => 'profile@example.com',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'DJ',
            'last_name' => 'Profile',
        ]);
    }

    public function test_user_can_have_contact_profile_data(): void
    {
        $user = User::factory()->create();

        $profile = UserProfile::create([
            'user_id' => $user->id,
            'contact_email' => 'bookings@example.com',
            'phone' => '555-0100',
            'city' => 'Atlanta',
            'state' => 'GA',
            'country' => 'US',
            'timezone' => 'America/New_York',
            'website_url' => 'https://example.com',
            'bio' => 'Open format DJ and battle fan.',
            'marketing_opt_in' => true,
        ]);

        $this->assertTrue($profile->marketing_opt_in);
        $this->assertSame('bookings@example.com', $user->profile()->first()?->contact_email);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'city' => 'Atlanta',
            'state' => 'GA',
        ]);
    }
}
