<?php

namespace Tests\Feature;

use App\Models\Mix;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\UserGamificationStat;
use Database\Seeders\GamificationActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_public_mixes_endpoint_paginates_mixes_at_fifteen_per_page(): void
    {
        $user = User::factory()->create(['name' => 'DJ Pages']);

        for ($index = 1; $index <= 30; $index++) {
            Mix::query()->create([
                'user_id' => $user->id,
                'title' => "Paged Mix {$index}",
                'is_public' => true,
                'published_at' => now()->subMinutes($index),
            ]);
        }

        $this->getJson('/api/mixes?per_page=100')
            ->assertOk()
            ->assertJsonCount(15, 'mixes')
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.per_page', 15)
            ->assertJsonPath('pagination.total', 30)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.from', 1)
            ->assertJsonPath('pagination.to', 15)
            ->assertJsonPath('pagination.has_more_pages', true)
            ->assertJsonPath('mixes.0.title', 'Paged Mix 1')
            ->assertJsonPath('mixes.14.title', 'Paged Mix 15');

        $this->getJson('/api/mixes?page=2&per_page=15')
            ->assertOk()
            ->assertJsonCount(15, 'mixes')
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.per_page', 15)
            ->assertJsonPath('pagination.from', 16)
            ->assertJsonPath('pagination.to', 30)
            ->assertJsonPath('pagination.has_more_pages', false)
            ->assertJsonPath('mixes.0.title', 'Paged Mix 16')
            ->assertJsonPath('mixes.14.title', 'Paged Mix 30');
    }

    public function test_public_mixes_endpoint_can_sort_by_top_rated_mixes(): void
    {
        $user = User::factory()->create(['name' => 'DJ Ratings']);

        Mix::query()->create([
            'user_id' => $user->id,
            'title' => 'Newest Unrated',
            'is_public' => true,
            'published_at' => now(),
            'play_count' => 500,
            'rating_average' => 0,
            'rating_count' => 0,
        ]);

        Mix::query()->create([
            'user_id' => $user->id,
            'title' => 'Solid Four',
            'is_public' => true,
            'published_at' => now()->subMinutes(5),
            'play_count' => 200,
            'rating_average' => 4,
            'rating_count' => 20,
        ]);

        Mix::query()->create([
            'user_id' => $user->id,
            'title' => 'Five Star Heat',
            'is_public' => true,
            'published_at' => now()->subMinutes(10),
            'play_count' => 100,
            'rating_average' => 5,
            'rating_count' => 2,
        ]);

        $this->getJson('/api/mixes?sort=top&per_page=3')
            ->assertOk()
            ->assertJsonPath('mixes.0.title', 'Five Star Heat')
            ->assertJsonPath('mixes.1.title', 'Solid Four')
            ->assertJsonPath('mixes.2.title', 'Newest Unrated');
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

    public function test_authenticated_users_can_save_list_and_remove_their_playlist_mixes(): void
    {
        $this->seed(GamificationActionSeeder::class);
        $owner = User::factory()->create(['name' => 'DJ Owner']);
        $listener = User::factory()->create();

        $mix = Mix::query()->create([
            'user_id' => $owner->id,
            'title' => 'Saved Favorite',
            'genre' => 'House',
            'audio_file' => 'media/mixes/saved-favorite.mp3',
            'cover_image' => 'media/mixes/saved-favorite.jpg',
            'duration' => 180,
            'is_public' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($listener)
            ->getJson('/api/user-playlist')
            ->assertOk()
            ->assertJsonCount(0, 'playlist');

        $this->actingAs($listener)
            ->postJson("/api/user-playlist/mixes/{$mix->id}")
            ->assertCreated()
            ->assertJsonPath('item.mix.id', $mix->id)
            ->assertJsonPath('item.mix.audio_url', '/storage/media/mixes/saved-favorite.mp3');

        $this->actingAs($listener)
            ->postJson("/api/user-playlist/mixes/{$mix->id}")
            ->assertOk()
            ->assertJsonPath('item.mix.id', $mix->id);

        $this->assertDatabaseHas('user_playlist_items', [
            'user_id' => $listener->id,
            'mix_id' => $mix->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('gamification_events', [
            'user_id' => $listener->id,
            'action_key' => 'mix_saved_to_playlist',
            'role_context' => 'fan',
            'xp_awarded' => 10,
            'target_type' => 'mix',
            'target_id' => $mix->id,
        ]);

        $this->assertSame(1, DB::table('gamification_events')
            ->where('user_id', $listener->id)
            ->where('action_key', 'mix_saved_to_playlist')
            ->where('target_type', 'mix')
            ->where('target_id', $mix->id)
            ->count());

        $stats = UserGamificationStat::query()->where('user_id', $listener->id)->firstOrFail();

        $this->assertSame(0, (int) $stats->dj_xp);
        $this->assertSame(10, (int) $stats->fan_xp);
        $this->assertSame(10, (int) $stats->total_xp);
        $this->assertSame(1, (int) $stats->fan_level);
        $this->assertNotNull($stats->last_activity_at);

        $this->actingAs($listener)
            ->getJson('/api/user-playlist')
            ->assertOk()
            ->assertJsonCount(1, 'playlist')
            ->assertJsonPath('playlist.0.mix.title', 'Saved Favorite');

        $this->actingAs($listener)
            ->deleteJson("/api/user-playlist/mixes/{$mix->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('user_playlist_items', [
            'user_id' => $listener->id,
            'mix_id' => $mix->id,
        ]);
    }

    public function test_private_mixes_cannot_be_saved_to_user_playlist(): void
    {
        $mix = Mix::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Private Favorite',
            'audio_file' => 'media/mixes/private.mp3',
            'is_public' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/user-playlist/mixes/{$mix->id}")
            ->assertNotFound();
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

    public function test_duplicate_public_portfolio_mixes_keep_the_newest_upload(): void
    {
        $user = User::factory()->create(['name' => 'DJ Portfolio']);

        $oldFile = MediaFile::query()->create([
            'user_id' => $user->id,
            'name' => 'godstime-old.mp3',
            'original_name' => 'godstime-old.mp3',
            'disk' => 'public',
            'path' => 'media/portfolios/dj-portfolio/godstime-old.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
            'collection' => 'dj_media',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
            'metadata' => [
                'portfolio' => [
                    'title' => 'GodsTime',
                    'visibility' => 'public',
                    'media_kind' => 'mix',
                ],
            ],
        ]);

        $newFile = MediaFile::query()->create([
            'user_id' => $user->id,
            'name' => 'godstime-new.mp3',
            'original_name' => 'godstime-new.mp3',
            'disk' => 'public',
            'path' => 'media/portfolios/dj-portfolio/godstime-new.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
            'collection' => 'dj_media',
            'created_at' => now(),
            'updated_at' => now(),
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
            'audio_media_file_id' => $oldFile->id,
            'title' => 'GodsTime',
            'audio_file' => $oldFile->path,
            'is_public' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->getJson('/api/mixes')
            ->assertOk()
            ->assertJsonCount(1, 'mixes')
            ->assertJsonPath('mixes.0.audio_url', '/storage/media/portfolios/dj-portfolio/godstime-new.mp3');

        $this->assertDatabaseHas('mixes', [
            'audio_media_file_id' => $oldFile->id,
            'is_public' => false,
        ]);

        $this->assertDatabaseHas('mixes', [
            'audio_media_file_id' => $newFile->id,
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
