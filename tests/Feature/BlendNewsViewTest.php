<?php

namespace Tests\Feature;

use App\Models\NewsTrendingMetric;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlendNewsViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_index_loads_latest_trending_metric_without_ambiguous_columns(): void
    {
        $post = Post::query()->create([
            'content_type' => Post::TYPE_NEWS,
            'title' => 'RSS Story Ready for Readers',
            'slug' => 'rss-story-ready-for-readers',
            'excerpt' => 'A published BlendNews story.',
            'content' => 'A published BlendNews story with a trending metric.',
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        NewsTrendingMetric::query()->create([
            'post_id' => $post->id,
            'views' => 42,
            'comments_count' => 3,
            'engagement_score' => 45,
            'window_started_at' => now()->startOfDay(),
            'window_ended_at' => now()->endOfDay(),
        ]);

        $this->get('/news')
            ->assertOk()
            ->assertSee('RSS Story Ready for Readers')
            ->assertSee('42');
    }
}
