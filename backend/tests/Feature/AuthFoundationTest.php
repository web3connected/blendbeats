<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_health_endpoint_responds(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'service' => 'blendbeats-api',
            ]);
    }

    public function test_users_and_admins_are_separate_auth_tables(): void
    {
        $user = User::create([
            'name' => 'Battle Fan',
            'email' => 'fan@example.com',
            'password' => 'password',
        ]);

        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
        ]);

        $this->assertDatabaseHas('users', ['email' => $user->email]);
        $this->assertDatabaseHas('admins', ['email' => $admin->email]);
    }

    public function test_admin_middleware_redirects_guest_to_admin_login(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_api_middleware_returns_admin_unauthenticated_response(): void
    {
        $this->getJson('/api/admin/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated admin.',
            ]);
    }

    public function test_user_api_middleware_returns_user_unauthenticated_response(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated user.',
            ]);
    }

    public function test_admin_registration_route_does_not_exist(): void
    {
        $this->get('/admin/register')
            ->assertNotFound();
    }

    public function test_admin_can_request_password_reset_link(): void
    {
        Notification::fake();

        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
        ]);

        $this->post('/admin/password/email', [
            'email' => $admin->email,
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('admin_password_reset_tokens', [
            'email' => $admin->email,
        ]);

        Notification::assertSentTo($admin, ResetPassword::class);
    }

    public function test_admin_can_reset_password(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
        ]);

        $token = Password::broker('admins')->createToken($admin);

        $this->post('/admin/password/reset', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect('/admin/login');

        $this->assertTrue(Hash::check('NewPassword123!', $admin->fresh()->password));
        $this->assertDatabaseMissing('admin_password_reset_tokens', [
            'email' => $admin->email,
        ]);
    }

    public function test_admin_seeder_creates_documented_admin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->assertDatabaseHas('admins', [
            'name' => 'BlendBeats Admin',
            'email' => 'admin@blendbeats.local',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_account_page_shows_current_admin_details(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/account')
            ->assertOk()
            ->assertSee('Site Admin')
            ->assertSee('admin@example.com')
            ->assertSee('Sys Admin')
            ->assertSee('Active');
    }

    public function test_admin_can_update_account_profile(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/account/profile', [
                'name' => 'Updated Admin',
                'email' => 'updated@example.com',
            ])
            ->assertRedirect('/admin/account');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'name' => 'Updated Admin',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_admin_can_update_account_password_separately(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/account/password', [
                'current_password' => 'password',
                'password' => 'ChangedPassword123!',
                'password_confirmation' => 'ChangedPassword123!',
            ])
            ->assertRedirect('/admin/account?tab=password');

        $this->assertTrue(Hash::check('ChangedPassword123!', $admin->fresh()->password));
    }

    public function test_admin_users_index_lists_admin_accounts(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admin-center/admin-users')
            ->assertOk()
            ->assertSee('Admin Center')
            ->assertSee('Site Admin')
            ->assertSee('admin@example.com');
    }

    public function test_admin_can_create_admin_user(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/admin-center/admin-users', [
                'name' => 'Second Admin',
                'email' => 'second@example.com',
                'role' => 'admin',
                'is_active' => '1',
                'password' => 'AdminPassword123!',
                'password_confirmation' => 'AdminPassword123!',
            ])
            ->assertRedirect('/admin/admin-center/admin-users');

        $this->assertDatabaseHas('admins', [
            'name' => 'Second Admin',
            'email' => 'second@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_admin_user(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $target = Admin::create([
            'name' => 'Second Admin',
            'email' => 'second@example.com',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->put("/admin/admin-center/admin-users/{$target->id}", [
                'name' => 'Updated Second Admin',
                'email' => 'updated-second@example.com',
                'role' => 'sys-admin',
                'is_active' => '1',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect("/admin/admin-center/admin-users/{$target->id}/edit");

        $this->assertDatabaseHas('admins', [
            'id' => $target->id,
            'name' => 'Updated Second Admin',
            'email' => 'updated-second@example.com',
            'role' => 'sys-admin',
        ]);
    }

    public function test_admin_cannot_delete_own_admin_user(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete("/admin/admin-center/admin-users/{$admin->id}")
            ->assertRedirect('/admin/admin-center/admin-users');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'email' => 'admin@example.com',
        ]);
    }

    public function test_user_accounts_index_lists_user_accounts(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Battle Fan',
            'email' => 'fan@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admin-center/user-accounts')
            ->assertOk()
            ->assertSee('User Accounts')
            ->assertSee('Battle Fan')
            ->assertSee('fan@example.com');
    }

    public function test_admin_can_create_user_account(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/admin-center/user-accounts', [
                'name' => 'New Fan',
                'email' => 'new-fan@example.com',
                'password' => 'UserPassword123!',
                'password_confirmation' => 'UserPassword123!',
            ])
            ->assertRedirect('/admin/admin-center/user-accounts');

        $this->assertDatabaseHas('users', [
            'name' => 'New Fan',
            'email' => 'new-fan@example.com',
        ]);
    }

    public function test_admin_can_update_user_account(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $target = User::create([
            'name' => 'Battle Fan',
            'email' => 'fan@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($admin, 'admin')
            ->put("/admin/admin-center/user-accounts/{$target->id}", [
                'name' => 'Updated Fan',
                'email' => 'updated-fan@example.com',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect("/admin/admin-center/user-accounts/{$target->id}/edit");

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Fan',
            'email' => 'updated-fan@example.com',
        ]);
    }

    public function test_admin_can_delete_user_account(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $target = User::create([
            'name' => 'Battle Fan',
            'email' => 'fan@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($admin, 'admin')
            ->delete("/admin/admin-center/user-accounts/{$target->id}")
            ->assertRedirect('/admin/admin-center/user-accounts');

        $this->assertDatabaseMissing('users', [
            'id' => $target->id,
            'email' => 'fan@example.com',
        ]);
    }

    public function test_admin_role_seeder_creates_admin_app_roles(): void
    {
        $this->seed(AdminRoleSeeder::class);

        foreach (['sys-admin', 'admin', 'content-manager', 'support', 'viewer'] as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role,
                'guard_name' => 'admin',
            ]);
        }

        $this->assertTrue(Role::findByName('sys-admin', 'admin')->hasPermissionTo('roles.manage'));
    }

    public function test_sys_admin_can_open_role_manager(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = Admin::where('email', 'admin@blendbeats.local')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get('/admin/admin-center/role-manager')
            ->assertOk()
            ->assertSee('Role Manager')
            ->assertSee('Sys Admin')
            ->assertSee('Content Manager');
    }
}
