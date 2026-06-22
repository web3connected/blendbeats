<?php

namespace Tests\Feature;

use App\Models\Mix;
use App\Models\User;
use App\Models\UserGamificationStat;
use Database\Seeders\GamificationActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RatingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_rate_public_mixes_and_update_aggregates(): void
    {
        $owner = User::factory()->create();
        $rater = User::factory()->create();
        $secondRater = User::factory()->create();

        $mix = Mix::query()->create([
            'user_id' => $owner->id,
            'title' => 'Rating Ready Mix',
            'is_public' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($rater)
            ->postJson("/api/ratings/mixes/{$mix->id}", [
                'rating' => 5,
                'review' => 'Strong set.',
            ])
            ->assertCreated()
            ->assertJsonPath('rating.average', 5)
            ->assertJsonPath('rating.count', 1)
            ->assertJsonPath('rating.user_rating', 5);

        $this->actingAs($secondRater)
            ->postJson("/api/ratings/mixes/{$mix->id}", [
                'rating' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('rating.average', 4)
            ->assertJsonPath('rating.count', 2);

        $this->actingAs($rater)
            ->postJson("/api/ratings/mixes/{$mix->id}", [
                'rating' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('rating.average', 3.5)
            ->assertJsonPath('rating.count', 2)
            ->assertJsonPath('rating.user_rating', 4);

        $this->assertDatabaseHas('mixes', [
            'id' => $mix->id,
            'rating_average' => 3.5,
            'rating_count' => 2,
        ]);
    }

    public function test_mix_rating_awards_fan_xp_once(): void
    {
        $this->seed(GamificationActionSeeder::class);

        $owner = User::factory()->create();
        $rater = User::factory()->create();

        $mix = Mix::query()->create([
            'user_id' => $owner->id,
            'title' => 'XP Rated Mix',
            'is_public' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($rater)
            ->postJson("/api/ratings/mixes/{$mix->id}", ['rating' => 5])
            ->assertCreated()
            ->assertJsonPath('rating.user_rating', 5);

        $this->actingAs($rater)
            ->postJson("/api/ratings/mixes/{$mix->id}", ['rating' => 4])
            ->assertCreated()
            ->assertJsonPath('rating.user_rating', 4);

        $this->assertDatabaseHas('gamification_events', [
            'user_id' => $rater->id,
            'action_key' => 'mix_liked',
            'role_context' => 'fan',
            'xp_awarded' => 5,
            'target_type' => 'mix',
            'target_id' => $mix->id,
        ]);

        $this->assertSame(1, DB::table('gamification_events')
            ->where('user_id', $rater->id)
            ->where('action_key', 'mix_liked')
            ->where('target_type', 'mix')
            ->where('target_id', $mix->id)
            ->count());

        $stats = UserGamificationStat::query()->where('user_id', $rater->id)->firstOrFail();

        $this->assertSame(0, (int) $stats->dj_xp);
        $this->assertSame(5, (int) $stats->fan_xp);
        $this->assertSame(5, (int) $stats->total_xp);
        $this->assertSame(1, (int) $stats->fan_level);
        $this->assertNotNull($stats->last_activity_at);
    }

    public function test_user_can_remove_their_rating(): void
    {
        $owner = User::factory()->create();
        $rater = User::factory()->create();

        $mix = Mix::query()->create([
            'user_id' => $owner->id,
            'title' => 'Rated Mix',
            'is_public' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($rater)
            ->postJson("/api/ratings/mixes/{$mix->id}", ['rating' => 5])
            ->assertCreated();

        $this->actingAs($rater)
            ->deleteJson("/api/ratings/mixes/{$mix->id}")
            ->assertOk()
            ->assertJsonPath('rating.average', 0)
            ->assertJsonPath('rating.count', 0)
            ->assertJsonPath('rating.user_rating', null);

        $this->assertDatabaseHas('mixes', [
            'id' => $mix->id,
            'rating_average' => 0,
            'rating_count' => 0,
        ]);
    }

    public function test_private_mixes_cannot_be_rated_publicly(): void
    {
        $mix = Mix::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Private Mix',
            'is_public' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/ratings/mixes/{$mix->id}", ['rating' => 5])
            ->assertNotFound();
    }
}
