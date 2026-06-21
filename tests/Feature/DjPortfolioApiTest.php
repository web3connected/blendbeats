<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DjPortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_activate_and_manage_portfolio_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'DJ Portfolio']);

        $this->actingAs($user)
            ->postJson('/api/media/setup')
            ->assertOk()
            ->assertJsonPath('media_account.status', 'active')
            ->assertJsonPath('features.0.feature_key', 'media_library');

        $this->actingAs($user)
            ->getJson('/api/media/files?disk=public&collection=dj_media')
            ->assertOk()
            ->assertJsonPath('files', []);

        $fileId = $this->actingAs($user)
            ->postJson('/api/media/files', [
                'file' => UploadedFile::fake()->create('first-mix.mp3', 128, 'audio/mpeg'),
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'First Mix',
                'description' => 'Opening set with scratches.',
                'genre' => 'Hip-Hop',
                'visibility' => 'draft',
                'media_kind' => 'mix',
            ])
            ->assertCreated()
            ->assertJsonPath('file.original_name', 'first-mix.mp3')
            ->assertJsonPath('file.is_audio', true)
            ->assertJsonPath('file.portfolio_title', 'First Mix')
            ->assertJsonPath('file.portfolio_genre', 'Hip-Hop')
            ->assertJsonPath('file.portfolio_visibility', 'draft')
            ->assertJsonPath('file.path', fn (string $path): bool => str_starts_with($path, 'media/portfolios/dj-portfolio/'))
            ->assertJsonPath('file.url', fn (string $url): bool => str_contains($url, '/media/portfolios/dj-portfolio/'))
            ->json('file.id');

        $this->actingAs($user)
            ->deleteJson("/api/media/files/{$fileId}")
            ->assertOk()
            ->assertJsonPath('deleted', true);
    }

    public function test_instagram_source_requires_instagram_url(): void
    {
        $user = User::factory()->create(['name' => 'DJ Portfolio']);

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'external_url' => 'https://example.com/reel/not-instagram',
                'source_type' => 'instagram',
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Promo Reel',
                'visibility' => 'public',
                'media_kind' => 'video',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('external_url');
    }

    public function test_instagram_source_saves_portfolio_metadata(): void
    {
        $user = User::factory()->create(['name' => 'DJ Portfolio']);
        $instagramUrl = 'https://www.instagram.com/reel/C1234567890/';

        $fileId = $this->actingAs($user)
            ->postJson('/api/media/files', [
                'external_url' => $instagramUrl,
                'source_type' => 'instagram',
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Promo Reel',
                'description' => 'Event highlight reel.',
                'genre' => 'Open Format',
                'visibility' => 'public',
                'media_kind' => 'video',
            ])
            ->assertCreated()
            ->assertJsonPath('file.source_type', 'instagram')
            ->assertJsonPath('file.external_provider', 'instagram')
            ->assertJsonPath('file.external_url', $instagramUrl)
            ->assertJsonPath('file.embed_url', null)
            ->assertJsonPath('file.thumbnail_url', null)
            ->assertJsonPath('file.url', $instagramUrl)
            ->assertJsonPath('file.metadata.portfolio.source_type', 'instagram')
            ->assertJsonPath('file.metadata.portfolio.external_provider', 'instagram')
            ->assertJsonPath('file.metadata.portfolio.external_url', $instagramUrl)
            ->assertJsonPath('file.metadata.portfolio.embed_url', null)
            ->assertJsonPath('file.metadata.portfolio.thumbnail_url', null)
            ->json('file.id');

        $mediaFile = MediaFile::query()->findOrFail($fileId);

        $this->assertSame('instagram', $mediaFile->metadata['portfolio']['source_type']);
        $this->assertSame('instagram', $mediaFile->metadata['portfolio']['external_provider']);
        $this->assertSame($instagramUrl, $mediaFile->metadata['portfolio']['external_url']);
        $this->assertNull($mediaFile->metadata['portfolio']['embed_url']);
        $this->assertNull($mediaFile->metadata['portfolio']['thumbnail_url']);
    }

    public function test_external_portfolio_url_can_be_replaced_with_instagram_metadata(): void
    {
        $user = User::factory()->create(['name' => 'DJ Portfolio']);
        $originalUrl = 'https://www.instagram.com/reel/C1234567890/';
        $replacementUrl = 'https://www.instagram.com/p/C0987654321/';

        $fileId = $this->actingAs($user)
            ->postJson('/api/media/files', [
                'external_url' => $originalUrl,
                'source_type' => 'instagram',
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Promo Reel',
                'visibility' => 'public',
                'media_kind' => 'video',
            ])
            ->assertCreated()
            ->json('file.id');

        $this->actingAs($user)
            ->patchJson("/api/media/files/{$fileId}", [
                'external_url' => $replacementUrl,
                'source_type' => 'instagram',
            ])
            ->assertOk()
            ->assertJsonPath('file.source_type', 'instagram')
            ->assertJsonPath('file.external_provider', 'instagram')
            ->assertJsonPath('file.external_url', $replacementUrl)
            ->assertJsonPath('file.embed_url', null)
            ->assertJsonPath('file.thumbnail_url', null)
            ->assertJsonPath('file.metadata.portfolio.external_url', $replacementUrl);

        $mediaFile = MediaFile::query()->findOrFail($fileId);

        $this->assertSame('instagram', $mediaFile->metadata['portfolio']['source_type']);
        $this->assertSame('instagram', $mediaFile->metadata['portfolio']['external_provider']);
        $this->assertSame($replacementUrl, $mediaFile->metadata['portfolio']['external_url']);
        $this->assertNull($mediaFile->metadata['portfolio']['embed_url']);
        $this->assertNull($mediaFile->metadata['portfolio']['thumbnail_url']);
    }

    public function test_external_portfolio_url_can_be_replaced_with_youtube_metadata(): void
    {
        $user = User::factory()->create(['name' => 'DJ Portfolio']);
        $instagramUrl = 'https://www.instagram.com/reel/C1234567890/';

        $fileId = $this->actingAs($user)
            ->postJson('/api/media/files', [
                'external_url' => $instagramUrl,
                'source_type' => 'instagram',
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Promo Reel',
                'visibility' => 'public',
                'media_kind' => 'video',
            ])
            ->assertCreated()
            ->json('file.id');

        $this->actingAs($user)
            ->patchJson("/api/media/files/{$fileId}", [
                'external_url' => 'https://youtu.be/dQw4w9WgXcQ',
                'source_type' => 'youtube',
            ])
            ->assertOk()
            ->assertJsonPath('file.source_type', 'youtube')
            ->assertJsonPath('file.external_provider', 'youtube')
            ->assertJsonPath('file.external_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertJsonPath('file.embed_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ')
            ->assertJsonPath('file.thumbnail_url', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
            ->assertJsonPath('file.metadata.portfolio.external_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $mediaFile = MediaFile::query()->findOrFail($fileId);

        $this->assertSame('youtube', $mediaFile->metadata['portfolio']['source_type']);
        $this->assertSame('youtube', $mediaFile->metadata['portfolio']['external_provider']);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $mediaFile->metadata['portfolio']['external_url']);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $mediaFile->metadata['portfolio']['embed_url']);
        $this->assertSame('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $mediaFile->metadata['portfolio']['thumbnail_url']);
    }

    public function test_external_portfolio_url_can_be_replaced_with_instagram_metadata_from_youtube(): void
    {
        $user = User::factory()->create(['name' => 'DJ Portfolio']);
        $instagramUrl = 'https://www.instagram.com/reel/C9876543210/';

        $fileId = $this->actingAs($user)
            ->postJson('/api/media/files', [
                'external_url' => 'https://youtu.be/dQw4w9WgXcQ',
                'source_type' => 'youtube',
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Promo Video',
                'visibility' => 'public',
                'media_kind' => 'video',
            ])
            ->assertCreated()
            ->json('file.id');

        $this->actingAs($user)
            ->patchJson("/api/media/files/{$fileId}", [
                'external_url' => $instagramUrl,
                'source_type' => 'instagram',
            ])
            ->assertOk()
            ->assertJsonPath('file.source_type', 'instagram')
            ->assertJsonPath('file.external_provider', 'instagram')
            ->assertJsonPath('file.external_url', $instagramUrl)
            ->assertJsonPath('file.embed_url', null)
            ->assertJsonPath('file.thumbnail_url', null)
            ->assertJsonPath('file.metadata.portfolio.external_url', $instagramUrl);

        $mediaFile = MediaFile::query()->findOrFail($fileId);

        $this->assertSame('instagram', $mediaFile->metadata['portfolio']['source_type']);
        $this->assertSame('instagram', $mediaFile->metadata['portfolio']['external_provider']);
        $this->assertSame($instagramUrl, $mediaFile->metadata['portfolio']['external_url']);
        $this->assertNull($mediaFile->metadata['portfolio']['embed_url']);
        $this->assertNull($mediaFile->metadata['portfolio']['thumbnail_url']);
    }

    public function test_public_profile_exposes_instagram_portfolio_link(): void
    {
        $user = User::factory()->create(['name' => 'DJ Portfolio']);
        $instagramUrl = 'https://www.instagram.com/reel/C1234567890/';

        DB::table('dj_profiles')->insert([
            'user_id' => $user->id,
            'dj_name' => 'DJ Portfolio',
            'handle' => 'dj-portfolio',
            'profile_status' => 'active',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'external_url' => $instagramUrl,
                'source_type' => 'instagram',
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Promo Reel',
                'visibility' => 'public',
                'media_kind' => 'video',
            ])
            ->assertCreated();

        $this->getJson('/api/dj-hub/djs/dj-portfolio')
            ->assertOk()
            ->assertJsonPath('dj.portfolio_media.0.title', 'Promo Reel')
            ->assertJsonPath('dj.portfolio_media.0.url', $instagramUrl)
            ->assertJsonPath('dj.portfolio_media.0.external_provider', 'instagram');
    }
}
