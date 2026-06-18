<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Notifications\AdminPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_redirects_guests_to_admin_login(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_active_admin_can_log_in_to_dashboard(): void
    {
        Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticated('admin');
    }

    public function test_admin_login_screen_links_to_password_reset(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Forgot your admin password?')
            ->assertSee(route('admin.password.request'), false);
    }

    public function test_active_admin_can_request_password_reset_link(): void
    {
        Notification::fake();

        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);

        $this->post('/admin/password/email', [
            'email' => 'admin@example.com',
        ])->assertSessionHas('status');

        Notification::assertSentTo(
            $admin,
            AdminPasswordResetNotification::class,
            fn (AdminPasswordResetNotification $notification) => $notification->email === 'admin@example.com'
                && filled($notification->token),
        );
    }

    public function test_inactive_admin_does_not_receive_password_reset_link(): void
    {
        Notification::fake();

        Admin::query()->create([
            'name' => 'Inactive Admin',
            'email' => 'inactive@example.com',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $this->post('/admin/password/email', [
            'email' => 'inactive@example.com',
        ])->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_active_admin_can_reset_password_with_valid_token(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);

        $token = Password::broker('admins')->createToken($admin);

        $this->post('/admin/password/reset', [
            'token' => $token,
            'email' => 'admin@example.com',
            'password' => 'new-admin-password',
            'password_confirmation' => 'new-admin-password',
        ])->assertRedirect('/admin/login');

        $this->assertTrue(Hash::check('new-admin-password', $admin->fresh()->password));

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'new-admin-password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticated('admin');
    }

    public function test_authenticated_admin_can_view_dashboard(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Administration foundation');
    }

    public function test_public_home_remains_separate_from_admin_guard(): void
    {
        $this->get('/')
            ->assertOk();
    }
}
