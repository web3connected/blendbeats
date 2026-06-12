<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}
