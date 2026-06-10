<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticated('admin');
    }

    public function test_authenticated_admin_can_view_dashboard(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
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
