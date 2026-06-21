<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthFrontendApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_user_can_register_and_read_current_session_user(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'DJ Session',
            'email' => 'session@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'session@example.com')
            ->assertJsonPath('user.dj_profile', null);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'session@example.com');

        $user = User::query()->where('email', 'session@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_ad_credits', [
            'user_id' => $user->id,
            'credit_type' => 'featured_ad_day',
            'source' => 'registration_bonus',
            'duration_days' => 1,
            'quantity' => 1,
            'remaining_quantity' => 1,
            'discount_type' => 'percent',
            'discount_value' => 100,
            'status' => 'active',
        ]);
    }

    public function test_frontend_user_can_login_and_logout(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'login@example.com');

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user', null);
    }

    public function test_frontend_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            fn (ResetPassword $notification): bool => filled($notification->token),
        );
    }

    public function test_frontend_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-login@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::broker('users')->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'reset-login@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));

        $this->postJson('/api/auth/login', [
            'email' => 'reset-login@example.com',
            'password' => 'new-password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'reset-login@example.com');
    }
}
