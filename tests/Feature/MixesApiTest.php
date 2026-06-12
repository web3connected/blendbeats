<?php

namespace Tests\Feature;

use App\Models\Mix;
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
