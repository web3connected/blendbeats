<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'role' => 'super_admin',
        ]);

        $this->assertDatabaseHas('users', ['email' => $user->email]);
        $this->assertDatabaseHas('admins', ['email' => $admin->email]);
    }
}
