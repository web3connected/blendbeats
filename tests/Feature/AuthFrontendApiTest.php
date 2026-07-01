<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGamificationStat;
use App\Services\WalletService;
use Database\Seeders\GamificationActionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'available_balance' => 500,
            'locked_balance' => 0,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletService::TYPE_BETA_GRANT,
            'amount' => 500,
            'description' => 'Beta signup test token grant.',
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

    public function test_successful_login_awards_daily_login_xp_once_per_day(): void
    {
        $this->seed(GamificationActionSeeder::class);

        $user = User::factory()->create([
            'email' => 'daily-login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'daily-login@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'daily-login@example.com');

        $this->assertDatabaseHas('gamification_events', [
            'user_id' => $user->id,
            'action_key' => 'daily_login',
            'role_context' => 'fan',
            'xp_awarded' => 10,
            'target_type' => 'daily_login',
            'target_id' => (int) now()->format('Ymd'),
        ]);

        $stats = UserGamificationStat::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(0, (int) $stats->dj_xp);
        $this->assertSame(10, (int) $stats->fan_xp);
        $this->assertSame(10, (int) $stats->total_xp);

        $this->postJson('/api/auth/logout')->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'daily-login@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'daily-login@example.com');

        $this->assertSame(1, DB::table('gamification_events')
            ->where('user_id', $user->id)
            ->where('action_key', 'daily_login')
            ->where('target_type', 'daily_login')
            ->where('target_id', (int) now()->format('Ymd'))
            ->count());

        $stats->refresh();

        $this->assertSame(10, (int) $stats->fan_xp);
        $this->assertSame(10, (int) $stats->total_xp);
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
