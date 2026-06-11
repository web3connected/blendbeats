<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_crud_pages_are_accessible(): void
    {
        $signedInAdmin = Admin::query()->create([
            'name' => 'Signed In Admin',
            'email' => 'signed-in@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $adminUser = Admin::query()->create([
            'name' => 'Managed Admin',
            'email' => 'managed@example.com',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($signedInAdmin, 'admin')
            ->get('/admin/admincenter/adminusers')
            ->assertOk()
            ->assertSee('Managed Admin')
            ->assertSee('Show')
            ->assertSee('Edit')
            ->assertDontSee('Password Reset')
            ->assertDontSee('Avatar Upload');

        $this->actingAs($signedInAdmin, 'admin')
            ->get('/admin/admincenter/adminusers/create')
            ->assertOk()
            ->assertSee('Create Admin User');

        $this->actingAs($signedInAdmin, 'admin')
            ->get("/admin/admincenter/adminusers/{$adminUser->id}")
            ->assertOk()
            ->assertSee('Managed Admin')
            ->assertSee('Admin User Details')
            ->assertSee('Email Verified At')
            ->assertSee('Use Gravatar')
            ->assertSee('Edit')
            ->assertDontSee('Password Reset')
            ->assertDontSee('Avatar Upload')
            ->assertDontSee('Avatar Controls');

        $this->actingAs($signedInAdmin, 'admin')
            ->get("/admin/admincenter/adminusers/{$adminUser->id}/edit")
            ->assertOk()
            ->assertSee('Edit Admin User')
            ->assertSee('Profile Info')
            ->assertSee('Password Reset')
            ->assertSee('Avatar Upload')
            ->assertSee('Avatar Controls');
    }

    public function test_admin_user_can_be_created_updated_password_reset_and_deleted(): void
    {
        $signedInAdmin = Admin::query()->create([
            'name' => 'Signed In Admin',
            'email' => 'signed-in@example.com',
            'password' => 'password',
            'role' => 'sys-admin',
            'is_active' => true,
        ]);

        $this->actingAs($signedInAdmin, 'admin')
            ->post('/admin/admincenter/adminusers', [
                'name' => 'Created Admin',
                'email' => 'created@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'admin',
                'is_active' => '1',
                'use_gravatar' => '1',
            ])
            ->assertRedirect();

        $adminUser = Admin::query()->where('email', 'created@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $adminUser->password));

        $this->actingAs($signedInAdmin, 'admin')
            ->put("/admin/admincenter/adminusers/{$adminUser->id}", [
                '_section' => 'profile',
                'name' => 'Updated Admin',
                'email' => 'updated@example.com',
                'role' => 'support',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.admincenter.adminusers.edit', $adminUser).'#profile-info');

        $adminUser->refresh();

        $this->assertSame('Updated Admin', $adminUser->name);
        $this->assertSame('updated@example.com', $adminUser->email);
        $this->assertSame('support', $adminUser->role);
        $this->assertFalse($adminUser->is_active);

        $this->actingAs($signedInAdmin, 'admin')
            ->put("/admin/admincenter/adminusers/{$adminUser->id}", [
                '_section' => 'password',
                'new_password' => 'new-password123',
                'new_password_confirmation' => 'new-password123',
            ])
            ->assertRedirect(route('admin.admincenter.adminusers.edit', $adminUser).'#password-reset');

        $this->assertTrue(Hash::check('new-password123', $adminUser->fresh()->password));

        Storage::fake('public');

        $this->actingAs($signedInAdmin, 'admin')
            ->put("/admin/admincenter/adminusers/{$adminUser->id}", [
                '_section' => 'avatar',
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
                'use_gravatar' => '0',
            ])
            ->assertRedirect(route('admin.admincenter.adminusers.edit', $adminUser).'#avatar-upload');

        $adminUser->refresh();

        $this->assertNotNull($adminUser->avatar);
        $this->assertFalse($adminUser->use_gravatar);
        Storage::disk('public')->assertExists($adminUser->avatar);

        $this->actingAs($signedInAdmin, 'admin')
            ->put("/admin/admincenter/adminusers/{$adminUser->id}", [
                '_section' => 'avatar',
                'use_gravatar' => '1',
            ])
            ->assertRedirect(route('admin.admincenter.adminusers.edit', $adminUser).'#avatar-upload');

        $this->assertTrue($adminUser->fresh()->use_gravatar);

        $this->actingAs($signedInAdmin, 'admin')
            ->delete("/admin/admincenter/adminusers/{$adminUser->id}")
            ->assertRedirect('/admin/admincenter/adminusers');

        $this->assertDatabaseMissing('admins', [
            'email' => 'updated@example.com',
        ]);
    }
}
