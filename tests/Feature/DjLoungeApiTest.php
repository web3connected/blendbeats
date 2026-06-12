<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DjLoungeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dj_lounge_posts_endpoint_returns_empty_feed_until_tables_exist(): void
    {
        $this->getJson('/api/dj-lounge/posts')
            ->assertOk()
            ->assertJsonPath('posts', []);
    }
}
