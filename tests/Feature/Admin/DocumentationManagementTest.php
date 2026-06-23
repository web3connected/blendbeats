<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_documentation_management_screen(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Documentation Admin',
            'email' => 'documentation-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/documentation')
            ->assertOk()
            ->assertSee('Documentation Center')
            ->assertSee('Getting Started')
            ->assertSee('Affiliate Program')
            ->assertSee('/account/docs/platform-overview')
            ->assertSee('/account/docs/affiliate-program');
    }

    public function test_documentation_management_requires_documentation_permission(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Documentation Manager',
            'email' => 'documentation-manager@example.com',
            'password' => 'password',
            'role' => 'manager',
            'is_active' => true,
        ]);
        $admin->syncRoles(['manager']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/documentation')
            ->assertForbidden();
    }
}
