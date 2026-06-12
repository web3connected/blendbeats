<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DjLoungeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dj_lounge_posts_endpoint_returns_empty_feed_until_tables_exist(): void
    {
        $this->getJson('/api/dj-lounge/posts')
            ->assertOk()
            ->assertJsonPath('posts', [])
            ->assertJsonPath('stats.postsToday', 0)
            ->assertJsonPath('stats.djsOnline', 0)
            ->assertJsonPath('stats.liveThreads', 0);
    }

    public function test_authenticated_user_can_post_and_create_two_tier_replies(): void
    {
        $user = User::factory()->create(['name' => 'DJ Reply']);

        $postId = $this->actingAs($user)
            ->postJson('/api/dj-lounge/posts', [
                'body' => 'Testing the lounge wall.',
            ])
            ->assertCreated()
            ->assertJsonPath('post.body', 'Testing the lounge wall.')
            ->json('post.id');

        $replyId = $this->actingAs($user)
            ->postJson("/api/dj-lounge/posts/{$postId}/replies", [
                'body' => 'First reply.',
            ])
            ->assertCreated()
            ->assertJsonPath('reply.body', 'First reply.')
            ->assertJsonPath('comment_count', 1)
            ->json('reply.id');

        $childReplyId = $this->actingAs($user)
            ->postJson("/api/dj-lounge/posts/{$postId}/replies", [
                'body' => 'Nested reply.',
                'parent_id' => $replyId,
            ])
            ->assertCreated()
            ->assertJsonPath('reply.body', 'Nested reply.')
            ->assertJsonPath('reply.parentId', (string) $replyId)
            ->assertJsonPath('comment_count', 2)
            ->json('reply.id');

        $this->actingAs($user)
            ->postJson("/api/dj-lounge/posts/{$postId}/replies", [
                'body' => 'Too deep.',
                'parent_id' => $childReplyId,
            ])
            ->assertUnprocessable();

        $this->getJson('/api/dj-lounge/posts')
            ->assertOk()
            ->assertJsonPath('stats.postsToday', 1)
            ->assertJsonPath('stats.djsOnline', 1)
            ->assertJsonPath('posts.0.comments', 2)
            ->assertJsonPath('posts.0.replies.0.body', 'First reply.')
            ->assertJsonPath('posts.0.replies.0.replies.0.body', 'Nested reply.');
    }
}
