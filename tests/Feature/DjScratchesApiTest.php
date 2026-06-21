<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DjScratchesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_scratches_endpoint_exposes_short_public_scratch_videos(): void
    {
        $user = User::factory()->create(['name' => 'DJ Cutter']);

        DB::table('dj_profiles')->insert([
            'user_id' => $user->id,
            'dj_name' => 'DJ Cutter',
            'handle' => 'dj-cutter',
            'profile_headline' => 'Cuts with style.',
            'profile_status' => 'active',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MediaFile::query()->create([
            'user_id' => $user->id,
            'name' => 'clean-routine.mp4',
            'original_name' => 'clean-routine.mp4',
            'disk' => 'public',
            'path' => 'media/portfolios/dj-cutter/clean-routine.mp4',
            'mime_type' => 'video/mp4',
            'size' => 2048,
            'collection' => 'dj_media',
            'metadata' => [
                'portfolio' => [
                    'title' => 'Clean Routine',
                    'description' => 'Quick juggle and scratch run.',
                    'genre' => 'Scratch Sets',
                    'visibility' => 'public',
                    'media_kind' => 'scratch',
                    'duration_seconds' => 172,
                ],
            ],
        ]);

        MediaFile::query()->create([
            'user_id' => $user->id,
            'name' => 'private-routine.mp4',
            'original_name' => 'private-routine.mp4',
            'disk' => 'public',
            'path' => 'media/portfolios/dj-cutter/private-routine.mp4',
            'mime_type' => 'video/mp4',
            'size' => 2048,
            'collection' => 'dj_media',
            'metadata' => [
                'portfolio' => [
                    'title' => 'Private Routine',
                    'visibility' => 'private',
                    'media_kind' => 'scratch',
                    'duration_seconds' => 90,
                ],
            ],
        ]);

        MediaFile::query()->create([
            'user_id' => $user->id,
            'name' => 'too-long.mp4',
            'original_name' => 'too-long.mp4',
            'disk' => 'public',
            'path' => 'media/portfolios/dj-cutter/too-long.mp4',
            'mime_type' => 'video/mp4',
            'size' => 2048,
            'collection' => 'dj_media',
            'metadata' => [
                'portfolio' => [
                    'title' => 'Too Long',
                    'visibility' => 'public',
                    'media_kind' => 'scratch',
                    'duration_seconds' => 301,
                ],
            ],
        ]);

        $this->getJson('/api/dj-scratches')
            ->assertOk()
            ->assertJsonCount(1, 'scratches')
            ->assertJsonPath('scratches.0.title', 'Clean Routine')
            ->assertJsonPath('scratches.0.dj.name', 'DJ Cutter')
            ->assertJsonPath('scratches.0.duration_seconds', 172)
            ->assertJsonPath('stats.scratch_count', 1)
            ->assertJsonPath('genres.0', 'Scratch Sets')
            ->assertJsonMissing(['title' => 'Private Routine'])
            ->assertJsonMissing(['title' => 'Too Long']);
    }

    public function test_scratch_video_uploads_must_be_five_minutes_or_less(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'DJ Long Cut']);

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'file' => UploadedFile::fake()->create('long-scratch.mp4', 1024, 'video/mp4'),
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Long Scratch',
                'visibility' => 'public',
                'media_kind' => 'scratch',
                'duration_seconds' => 301,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_seconds');
    }

    public function test_scratch_video_upload_allows_fractional_five_minute_metadata(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'DJ Exact Cut']);

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'file' => UploadedFile::fake()->create('five-minute-scratch.mp4', 1024, 'video/mp4'),
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Five Minute Scratch',
                'visibility' => 'public',
                'media_kind' => 'scratch',
                'duration_seconds' => 300.4,
            ])
            ->assertCreated()
            ->assertJsonPath('file.portfolio_kind', 'scratch')
            ->assertJsonPath('file.duration_seconds', 300.4);
    }

    public function test_scratch_routine_can_be_linked_from_youtube(): void
    {
        $user = User::factory()->create(['name' => 'DJ Link Cut']);

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'external_url' => 'https://youtu.be/dQw4w9WgXcQ',
                'source_type' => 'youtube',
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Linked Routine',
                'visibility' => 'public',
                'media_kind' => 'scratch',
            ])
            ->assertCreated()
            ->assertJsonPath('file.portfolio_kind', 'scratch')
            ->assertJsonPath('file.duration_seconds', null)
            ->assertJsonPath('file.source_type', 'youtube')
            ->assertJsonPath('file.external_provider', 'youtube')
            ->assertJsonPath('file.external_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertJsonPath('file.embed_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ')
            ->assertJsonPath('file.portfolio_cover_image_url', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg');

        $this->assertDatabaseHas('media_files', [
            'user_id' => $user->id,
            'name' => 'dQw4w9WgXcQ.youtube',
            'mime_type' => 'video/youtube',
            'size' => 0,
            'collection' => 'dj_media',
        ]);
    }

    public function test_scratch_routine_can_be_linked_from_instagram(): void
    {
        $user = User::factory()->create(['name' => 'DJ Insta Cut']);
        $instagramUrl = 'https://www.instagram.com/reel/C1234567890/';

        DB::table('dj_profiles')->insert([
            'user_id' => $user->id,
            'dj_name' => 'DJ Insta Cut',
            'handle' => 'dj-insta-cut',
            'profile_headline' => 'Instagram routines.',
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
                'title' => 'Instagram Routine',
                'visibility' => 'public',
                'media_kind' => 'scratch',
            ])
            ->assertCreated()
            ->assertJsonPath('file.portfolio_kind', 'scratch')
            ->assertJsonPath('file.duration_seconds', null)
            ->assertJsonPath('file.source_type', 'instagram')
            ->assertJsonPath('file.external_provider', 'instagram')
            ->assertJsonPath('file.external_url', $instagramUrl)
            ->assertJsonPath('file.embed_url', null)
            ->assertJsonPath('file.thumbnail_url', null);

        $this->getJson('/api/dj-scratches')
            ->assertOk()
            ->assertJsonCount(1, 'scratches')
            ->assertJsonPath('scratches.0.title', 'Instagram Routine')
            ->assertJsonPath('scratches.0.source_type', 'instagram')
            ->assertJsonPath('scratches.0.external_provider', 'instagram')
            ->assertJsonPath('scratches.0.url', $instagramUrl)
            ->assertJsonPath('scratches.0.embed_url', null)
            ->assertJsonPath('scratches.0.thumbnail_url', null);
    }

    public function test_youtube_scratch_routines_do_not_use_monthly_upload_limit(): void
    {
        $user = User::factory()->create(['name' => 'DJ Free Tube', 'media_storage_tier' => 'free']);

        $this->createMonthlyScratchUploads($user, 3);

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'source_type' => 'youtube',
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Unlimited Linked Routine',
                'visibility' => 'public',
                'media_kind' => 'scratch',
            ])
            ->assertCreated()
            ->assertJsonPath('file.portfolio_kind', 'scratch')
            ->assertJsonPath('file.source_type', 'youtube');
    }

    public function test_public_scratches_endpoint_exposes_youtube_linked_routines(): void
    {
        $user = User::factory()->create(['name' => 'DJ Tube Cut']);

        DB::table('dj_profiles')->insert([
            'user_id' => $user->id,
            'dj_name' => 'DJ Tube Cut',
            'handle' => 'dj-tube-cut',
            'profile_headline' => 'Linked routines.',
            'profile_status' => 'active',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MediaFile::query()->create([
            'user_id' => $user->id,
            'name' => 'dQw4w9WgXcQ.youtube',
            'original_name' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'disk' => 'public',
            'path' => "external/youtube/{$user->id}/dQw4w9WgXcQ_test",
            'mime_type' => 'video/youtube',
            'size' => 0,
            'collection' => 'dj_media',
            'metadata' => [
                'external_source' => [
                    'provider' => 'youtube',
                    'video_id' => 'dQw4w9WgXcQ',
                    'watch_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'embed_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
                    'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                ],
                'portfolio' => [
                    'title' => 'Tube Routine',
                    'description' => 'Routine hosted on YouTube.',
                    'genre' => 'Scratch Sets',
                    'visibility' => 'public',
                    'media_kind' => 'scratch',
                    'source_type' => 'youtube',
                    'external_provider' => 'youtube',
                    'external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'embed_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
                    'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                ],
            ],
        ]);

        $this->getJson('/api/dj-scratches')
            ->assertOk()
            ->assertJsonCount(1, 'scratches')
            ->assertJsonPath('scratches.0.title', 'Tube Routine')
            ->assertJsonPath('scratches.0.source_type', 'youtube')
            ->assertJsonPath('scratches.0.external_provider', 'youtube')
            ->assertJsonPath('scratches.0.url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertJsonPath('scratches.0.embed_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ')
            ->assertJsonPath('scratches.0.cover_image_url', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
    }

    public function test_free_tier_can_upload_three_scratch_videos_per_month(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'DJ Free Limit', 'media_storage_tier' => 'free']);

        $this->createMonthlyScratchUploads($user, 3);

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'file' => UploadedFile::fake()->create('fourth-scratch.mp4', 1024, 'video/mp4'),
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Fourth Scratch',
                'visibility' => 'public',
                'media_kind' => 'scratch',
                'duration_seconds' => 120,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('media_kind')
            ->assertJsonPath('errors.media_kind.0', 'Free includes 3 Scratch routine video uploads per month.');
    }

    public function test_plus_tier_can_upload_past_the_free_monthly_scratch_limit(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'DJ Plus Limit', 'media_storage_tier' => 'dj_plus']);

        $this->createMonthlyScratchUploads($user, 3);

        $this->actingAs($user)
            ->postJson('/api/media/files', [
                'file' => UploadedFile::fake()->create('plus-scratch.mp4', 1024, 'video/mp4'),
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'Plus Scratch',
                'visibility' => 'public',
                'media_kind' => 'scratch',
                'duration_seconds' => 120,
            ])
            ->assertCreated()
            ->assertJsonPath('file.portfolio_kind', 'scratch');
    }

    private function createMonthlyScratchUploads(User $user, int $count): void
    {
        for ($index = 1; $index <= $count; $index++) {
            MediaFile::query()->create([
                'user_id' => $user->id,
                'name' => "scratch-{$index}.mp4",
                'original_name' => "scratch-{$index}.mp4",
                'disk' => 'public',
                'path' => "media/portfolios/{$user->id}/scratch-{$index}.mp4",
                'mime_type' => 'video/mp4',
                'size' => 2048,
                'collection' => 'dj_media',
                'metadata' => [
                    'portfolio' => [
                        'title' => "Scratch {$index}",
                        'visibility' => 'public',
                        'media_kind' => 'scratch',
                        'duration_seconds' => 120,
                    ],
                ],
                'created_at' => now()->startOfMonth()->addDays($index),
                'updated_at' => now()->startOfMonth()->addDays($index),
            ]);
        }
    }
}
