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
                    'duration_seconds' => 181,
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

    public function test_scratch_video_uploads_must_be_three_minutes_or_less(): void
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
                'duration_seconds' => 181,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_seconds');
    }
}
