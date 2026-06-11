<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_crud_pages_are_accessible(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Signed In Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Managed User',
            'first_name' => 'Managed',
            'last_name' => 'User',
            'email' => 'managed@example.com',
            'password' => 'password',
            'media_storage_tier' => 'starter',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Managed User')
            ->assertSee('User Accounts');

        $this->actingAs($admin, 'admin')
            ->get('/admin/users/create')
            ->assertOk()
            ->assertSee('Create User')
            ->assertSee('Media Storage Tier');

        $this->actingAs($admin, 'admin')
            ->get("/admin/users/{$user->id}")
            ->assertOk()
            ->assertSee('User Details')
            ->assertSee('Media Storage Tier')
            ->assertSee('Edit')
            ->assertDontSee('Password Reset')
            ->assertDontSee('Avatar Upload')
            ->assertDontSee('Role Information')
            ->assertDontSee('Permission Count');

        $this->actingAs($admin, 'admin')
            ->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->assertSee('Edit User')
            ->assertSee('Profile Info')
            ->assertSee('Password Reset')
            ->assertSee('Avatar Upload')
            ->assertSee('Avatar Controls')
            ->assertDontSee('name="role_id"', false)
            ->assertDontSee('name="role"', false);
    }

    public function test_user_can_be_created_updated_password_reset_avatar_updated_and_deleted(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Signed In Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/users', [
                'name' => 'Created User',
                'first_name' => 'Created',
                'last_name' => 'User',
                'email' => 'created@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'media_storage_tier' => 'starter',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'created@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $user->password));

        $this->actingAs($admin, 'admin')
            ->put("/admin/users/{$user->id}", [
                '_section' => 'profile',
                'name' => 'Updated User',
                'first_name' => 'Updated',
                'last_name' => 'Person',
                'email' => 'updated@example.com',
                'media_storage_tier' => 'premium',
            ])
            ->assertRedirect(route('admin.users.edit', $user).'#profile-info');

        $user->refresh();

        $this->assertSame('Updated User', $user->name);
        $this->assertSame('Updated', $user->first_name);
        $this->assertSame('Person', $user->last_name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertSame('premium', $user->media_storage_tier);

        $this->actingAs($admin, 'admin')
            ->put("/admin/users/{$user->id}", [
                '_section' => 'password',
                'new_password' => 'new-password123',
                'new_password_confirmation' => 'new-password123',
            ])
            ->assertRedirect(route('admin.users.edit', $user).'#password-reset');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));

        Storage::fake('public');

        $this->actingAs($admin, 'admin')
            ->put("/admin/users/{$user->id}", [
                '_section' => 'avatar',
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
                'use_gravatar' => '0',
            ])
            ->assertRedirect(route('admin.users.edit', $user).'#avatar-upload');

        $user->refresh();

        $this->assertNotNull($user->avatar);
        $this->assertFalse($user->use_gravatar);
        $this->assertFalse($user->is_gravatar);
        Storage::disk('public')->assertExists($user->avatar);

        $this->actingAs($admin, 'admin')
            ->put("/admin/users/{$user->id}", [
                '_section' => 'avatar',
                'use_gravatar' => '1',
            ])
            ->assertRedirect(route('admin.users.edit', $user).'#avatar-upload');

        $this->assertTrue($user->fresh()->use_gravatar);
        $this->assertTrue($user->fresh()->is_gravatar);

        $this->actingAs($admin, 'admin')
            ->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->assertSee('data-uploaded-avatar', false)
            ->assertSee($user->fresh()->getUploadedAvatarUrl(), false);

        $this->actingAs($admin, 'admin')
            ->delete("/admin/users/{$user->id}")
            ->assertRedirect('/admin/users');

        $this->assertDatabaseMissing('users', [
            'email' => 'updated@example.com',
        ]);
    }
}
