<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_pages_are_accessible(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $signedInAdmin = Admin::query()->create([
            'name' => 'Signed In Admin',
            'email' => 'signed-in@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $signedInAdmin->syncRoles(['super-admin']);

        $role = Role::query()->where('name', 'manager')->where('guard_name', 'admin')->firstOrFail();

        $this->actingAs($signedInAdmin, 'admin')
            ->get('/admin/admincenter/adminroles')
            ->assertOk()
            ->assertSee('Admin Roles')
            ->assertSee('manager')
            ->assertSee('User Count');

        $this->actingAs($signedInAdmin, 'admin')
            ->get('/admin/admincenter/adminroles/create')
            ->assertOk()
            ->assertSee('Create Admin Role')
            ->assertSee('Permission Assignments');

        $this->actingAs($signedInAdmin, 'admin')
            ->get("/admin/admincenter/adminroles/{$role->id}")
            ->assertOk()
            ->assertSee('Role Information')
            ->assertSee('Assigned Permissions')
            ->assertSee('Assigned Users');

        $this->actingAs($signedInAdmin, 'admin')
            ->get("/admin/admincenter/adminroles/{$role->id}/edit")
            ->assertOk()
            ->assertSee('Edit Admin Role')
            ->assertSee('Permission Assignments');
    }

    public function test_admin_role_can_be_created_updated_and_deleted(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $signedInAdmin = Admin::query()->create([
            'name' => 'Signed In Admin',
            'email' => 'signed-in@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $signedInAdmin->syncRoles(['super-admin']);

        $permissions = Permission::query()
            ->where('guard_name', 'admin')
            ->whereIn('name', ['adminusers.view', 'adminusers.update'])
            ->pluck('name')
            ->all();

        $this->actingAs($signedInAdmin, 'admin')
            ->post('/admin/admincenter/adminroles', [
                'name' => 'qa-manager',
                'display_name' => 'QA Manager',
                'description' => 'Reviews user-facing workflows.',
                'permissions' => $permissions,
            ])
            ->assertRedirect();

        $role = Role::query()->where('name', 'qa-manager')->where('guard_name', 'admin')->firstOrFail();

        $this->assertSame('QA Manager', $role->display_name);
        $this->assertTrue($role->hasPermissionTo('adminusers.view'));

        $this->actingAs($signedInAdmin, 'admin')
            ->put("/admin/admincenter/adminroles/{$role->id}", [
                'name' => 'qa-lead',
                'display_name' => 'QA Lead',
                'description' => 'Leads user-facing workflow review.',
                'permissions' => ['adminusers.view'],
            ])
            ->assertRedirect(route('admin.admincenter.adminroles.edit', $role));

        $role->refresh();

        $this->assertSame('qa-lead', $role->name);
        $this->assertSame('QA Lead', $role->display_name);
        $this->assertTrue($role->hasPermissionTo('adminusers.view'));
        $this->assertFalse($role->hasPermissionTo('adminusers.update'));

        $this->actingAs($signedInAdmin, 'admin')
            ->delete("/admin/admincenter/adminroles/{$role->id}")
            ->assertRedirect('/admin/admincenter/adminroles');

        $this->assertDatabaseMissing('roles', [
            'name' => 'qa-lead',
            'guard_name' => 'admin',
        ]);
    }
}
