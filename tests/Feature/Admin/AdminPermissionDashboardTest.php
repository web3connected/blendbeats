<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_permissions_dashboard_displays_spatie_permissions(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Signed In Admin',
            'email' => 'signed-in@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/adminpermissions')
            ->assertOk()
            ->assertSee('Permissions')
            ->assertSee('adminusers.view')
            ->assertSee('Role Coverage')
            ->assertSee('Super Admin');
    }

    public function test_legacy_permissions_sidebar_url_redirects_to_dashboard(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Signed In Admin',
            'email' => 'signed-in@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admin-center/permissions')
            ->assertRedirect(route('admin.admincenter.adminpermissions.index'));
    }
}
