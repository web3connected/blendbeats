<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MediaAccount;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\MediaManagerService;
use App\Services\MediaSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_can_be_configured_for_media_and_upload_file(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Media User',
            'first_name' => 'Media',
            'last_name' => 'User',
            'email' => 'media-user@example.com',
            'password' => 'password',
            'media_storage_tier' => 'starter',
        ]);

        $payload = app(MediaSetupService::class)->setup($user);

        $this->assertSame('public', $payload['media_account']['disk']);
        $this->assertStringStartsWith('media/accounts/user-media-user', $payload['media_account']['root_path']);

        $file = app(MediaManagerService::class)->uploadForOwner(
            $user,
            UploadedFile::fake()->image('cover.jpg'),
            'public',
            MediaManagerService::COLLECTION_USER_AVATARS,
        );

        $this->assertInstanceOf(MediaFile::class, $file);
        $this->assertSame($user->id, $file->user_id);
        $this->assertNull($file->admin_id);
        $this->assertStringStartsWith('/storage/', parse_url($file->url, PHP_URL_PATH));
        Storage::disk('public')->assertExists($file->path);

        $this->actingAs($user);

        $this->assertTrue(app(MediaManagerService::class)->deleteMediaFile($file));
        Storage::disk('public')->assertMissing($file->path);
    }

    public function test_admin_user_can_be_configured_for_media_and_upload_file(): void
    {
        Storage::fake('public');

        $admin = Admin::query()->create([
            'name' => 'Media Admin',
            'email' => 'media-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);

        $payload = app(MediaSetupService::class)->setup($admin);

        $this->assertSame('public', $payload['media_account']['disk']);
        $this->assertStringStartsWith('media/accounts/admin-media-admin', $payload['media_account']['root_path']);

        $file = app(MediaManagerService::class)->uploadForOwner(
            $admin,
            UploadedFile::fake()->image('admin-cover.jpg'),
            'public',
            MediaManagerService::COLLECTION_ADMIN_LOCAL,
        );

        $this->assertInstanceOf(MediaFile::class, $file);
        $this->assertSame($admin->id, $file->admin_id);
        $this->assertNull($file->user_id);
        $this->assertSame(1, $admin->mediaFiles()->count());
        Storage::disk('public')->assertExists($file->path);
    }

    public function test_media_setup_is_idempotent_for_each_owner(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Repeat User',
            'email' => 'repeat-user@example.com',
            'password' => 'password',
            'media_storage_tier' => 'starter',
        ]);

        app(MediaSetupService::class)->setup($user);
        app(MediaSetupService::class)->setup($user);

        $this->assertSame(1, MediaAccount::query()->where('user_id', $user->id)->count());
    }

    public function test_existing_avatar_upload_still_uses_public_disk(): void
    {
        Storage::fake('public');

        $admin = Admin::query()->create([
            'name' => 'Signed In Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Avatar User',
            'email' => 'avatar-user@example.com',
            'password' => 'password',
            'media_storage_tier' => 'starter',
        ]);

        $this->actingAs($admin, 'admin')
            ->put("/admin/users/{$user->id}", [
                '_section' => 'avatar',
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
                'use_gravatar' => '0',
            ])
            ->assertRedirect(route('admin.users.edit', $user).'#avatar-upload');

        $user->refresh();

        $this->assertStringStartsWith('media/accounts/avatar/', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }
}
