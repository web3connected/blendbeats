<?php

namespace Tests\Feature;

use App\Models\Mix;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MixesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_mixes_endpoint_only_exposes_public_published_mixes(): void
    {
        $user = User::factory()->create(['name' => 'DJ Public']);

        $featured = Mix::query()->create([
            'user_id' => $user->id,
            'title' => 'Featured Heat',
            'genre' => 'Hip-Hop',
            'is_public' => true,
            'is_featured' => true,
            'play_count' => 120,
            'rating_average' => 4.8,
            'rating_count' => 12,
        ]);

        Mix::query()->create([
            'user_id' => $user->id,
            'title' => 'Public House',
            'genre' => 'House',
            'is_public' => true,
            'is_featured' => false,
            'play_count' => 30,
            'rating_average' => 4.2,
            'rating_count' => 4,
        ]);

        Mix::query()->create([
            'user_id' => $user->id,
            'title' => 'Private Featured',
            'genre' => 'Techno',
            'is_public' => false,
            'is_featured' => true,
            'play_count' => 999,
            'rating_average' => 5,
            'rating_count' => 1,
        ]);

        $response = $this->getJson('/api/mixes');

        $response
            ->assertOk()
            ->assertJsonPath('stats.featured_mixes', 1)
            ->assertJsonPath('stats.total_plays', 150)
            ->assertJsonPath('stats.genre_count', 2)
            ->assertJsonPath('featured.0.slug', $featured->slug)
            ->assertJsonMissing(['title' => 'Private Featured']);
    }

    public function test_play_endpoint_increments_public_mix_play_count(): void
    {
        $mix = Mix::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Playable Mix',
            'is_public' => true,
            'play_count' => 4,
        ]);

        $this->postJson("/api/mixes/{$mix->slug}/play")
            ->assertOk()
            ->assertJsonPath('play_count', 5);

        $this->assertDatabaseHas('mixes', [
            'id' => $mix->id,
            'play_count' => 5,
        ]);
    }

    public function test_public_portfolio_audio_uploads_are_exposed_as_public_mixes(): void
    {
        $user = User::factory()->create(['name' => 'DJ Portfolio']);

        MediaFile::query()->create([
            'user_id' => $user->id,
            'name' => 'late-night-blend.mp3',
            'original_name' => 'late-night-blend.mp3',
            'disk' => 'public',
            'path' => 'media/portfolios/dj-portfolio/late-night-blend.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
            'collection' => 'dj_media',
            'metadata' => [
                'portfolio' => [
                    'title' => 'Late Night Blend',
                    'description' => 'A public portfolio upload.',
                    'genre' => 'Hip-Hop',
                    'visibility' => 'public',
                    'media_kind' => 'mix',
                ],
            ],
        ]);

        $this->getJson('/api/mixes')
            ->assertOk()
            ->assertJsonPath('mixes.0.title', 'Late Night Blend')
            ->assertJsonPath('mixes.0.genre', 'Hip-Hop')
            ->assertJsonPath('mixes.0.audio_url', '/storage/media/portfolios/dj-portfolio/late-night-blend.mp3')
            ->assertJsonPath('stats.genre_count', 1);

        $this->assertDatabaseHas('mixes', [
            'user_id' => $user->id,
            'title' => 'Late Night Blend',
            'is_public' => true,
            'audio_file' => 'media/portfolios/dj-portfolio/late-night-blend.mp3',
        ]);
    }

    public function test_deleted_portfolio_audio_is_removed_from_public_mixes(): void
    {
        $user = User::factory()->create(['name' => 'DJ Portfolio']);

        $deletedFile = MediaFile::query()->create([
            'user_id' => $user->id,
            'name' => 'godstime-old.mp3',
            'original_name' => 'godstime-old.mp3',
            'disk' => 'public',
            'path' => 'media/portfolios/dj-portfolio/godstime-old.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
            'collection' => 'dj_media',
            'metadata' => [
                'portfolio' => [
                    'title' => 'GodsTime',
                    'visibility' => 'public',
                    'media_kind' => 'mix',
                ],
            ],
        ]);

        $activeFile = MediaFile::query()->create([
            'user_id' => $user->id,
            'name' => 'godstime-new.mp3',
            'original_name' => 'godstime-new.mp3',
            'disk' => 'public',
            'path' => 'media/portfolios/dj-portfolio/godstime-new.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
            'collection' => 'dj_media',
            'metadata' => [
                'portfolio' => [
                    'title' => 'GodsTime',
                    'genre' => 'Hip-Hop',
                    'visibility' => 'public',
                    'media_kind' => 'mix',
                ],
            ],
        ]);

        Mix::query()->create([
            'user_id' => $user->id,
            'audio_media_file_id' => $deletedFile->id,
            'title' => 'GodsTime',
            'audio_file' => $deletedFile->path,
            'is_public' => true,
            'published_at' => now()->subDay(),
        ]);

        $deletedFile->delete();

        $this->getJson('/api/mixes')
            ->assertOk()
            ->assertJsonCount(1, 'mixes')
            ->assertJsonPath('mixes.0.title', 'GodsTime')
            ->assertJsonPath('mixes.0.audio_url', '/storage/media/portfolios/dj-portfolio/godstime-new.mp3');

        $this->assertDatabaseHas('mixes', [
            'audio_media_file_id' => $deletedFile->id,
            'is_public' => false,
        ]);

        $this->assertDatabaseHas('mixes', [
            'audio_media_file_id' => $activeFile->id,
            'is_public' => true,
        ]);
    }

    public function test_play_endpoint_hides_private_mixes(): void
    {
        $mix = Mix::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Private Mix',
            'is_public' => false,
        ]);

        $this->postJson("/api/mixes/{$mix->slug}/play")->assertNotFound();
    }
}
