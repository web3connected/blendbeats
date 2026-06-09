<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\User;
use App\Models\UserFeatureActivation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaManagerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_list_and_delete_own_public_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Test User']);

        $this->actingAs($user)
            ->postJson('/api/media/setup')
            ->assertOk()
            ->assertJsonPath('media_account.account_slug', 'test-user')
            ->assertJsonPath('media_account.root_path', 'media/accounts/test-user')
            ->assertJsonPath('features.0.feature_key', UserFeatureActivation::MEDIA_LIBRARY);
        Storage::disk('public')->assertExists('media/accounts/test-user/audio');
        Storage::disk('public')->assertExists('media/accounts/test-user/video');
        Storage::disk('public')->assertExists('media/accounts/test-user/images');

        $response = $this->actingAs($user)
            ->postJson('/api/media/files', [
                'file' => UploadedFile::fake()->create('silconone-mix.mp3', 256, 'audio/mpeg'),
                'disk' => 'public',
                'collection' => 'dj_media',
            ])
            ->assertCreated()
            ->assertJsonPath('file.original_name', 'silconone-mix.mp3')
            ->assertJsonPath('file.collection', 'dj_media')
            ->assertJsonPath('file.is_audio', true)
            ->assertJsonPath('file.url', '/api/media/files/1/stream')
            ->assertJsonPath('quota.tier', 'starter')
            ->assertJsonPath('quota.limit_formatted', '500 MB');

        $fileId = $response->json('file.id');
        $mediaFile = MediaFile::findOrFail($fileId);

        $this->assertSame($user->mediaAccount()->value('id'), $mediaFile->media_account_id);
        $this->assertStringStartsWith('media/accounts/test-user/dj_media/', $mediaFile->path);
        Storage::disk('public')->assertExists($mediaFile->path);

        $this->actingAs($user)
            ->get("/api/media/files/{$fileId}/stream")
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg');

        $this->actingAs($user)
            ->getJson('/api/media/files?disk=public&collection=dj_media')
            ->assertOk()
            ->assertJsonPath('files.0.id', $fileId)
            ->assertJsonPath('files.0.original_name', 'silconone-mix.mp3')
            ->assertJsonPath('quota.tier_label', 'Starter');

        $this->actingAs($user)
            ->deleteJson("/api/media/files/{$fileId}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        Storage::disk('public')->assertMissing($mediaFile->path);

        $this->assertSoftDeleted('media_files', [
            'id' => $fileId,
        ]);
    }

    public function test_regular_user_cannot_delete_another_users_media(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $mediaFile = MediaFile::create([
            'user_id' => $owner->id,
            'name' => 'owned.mp3',
            'original_name' => 'owned.mp3',
            'disk' => 'public',
            'path' => 'dj_media/owned.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
            'collection' => 'dj_media',
        ]);

        Storage::disk('public')->put($mediaFile->path, 'audio');

        $this->actingAs($otherUser)
            ->deleteJson("/api/media/files/{$mediaFile->id}")
            ->assertUnauthorized();

        Storage::disk('public')->assertExists($mediaFile->path);
    }

    public function test_upload_is_rejected_when_user_exceeds_storage_tier_limit(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'media_storage_tier' => 'starter',
        ]);

        $this->actingAs($user)->postJson('/api/media/setup')->assertOk();

        MediaFile::create([
            'user_id' => $user->id,
            'name' => 'almost-full.mp3',
            'original_name' => 'almost-full.mp3',
            'disk' => 'public',
            'path' => 'dj_media/almost-full.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => (500 * 1024 * 1024) - 512,
            'collection' => 'dj_media',
        ]);

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'file' => UploadedFile::fake()->create('too-large.mp3', 1, 'audio/mpeg'),
                'disk' => 'public',
                'collection' => 'dj_media',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_requires_active_media_setup(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'file' => UploadedFile::fake()->create('before-setup.mp3', 1, 'audio/mpeg'),
                'disk' => 'public',
                'collection' => 'dj_media',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('media_account');
    }
}
