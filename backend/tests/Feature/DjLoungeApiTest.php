<?php

namespace Tests\Feature;

use App\Models\DjLoungePost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DjLoungeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_feed_lists_published_posts(): void
    {
        $user = User::create([
            'name' => 'DJ Nova',
            'email' => 'nova@example.com',
            'password' => 'password',
        ]);

        DjLoungePost::create([
            'user_id' => $user->id,
            'body' => 'First drop from the database lounge.',
            'genre' => 'Hip-Hop',
            'published_at' => now(),
        ]);

        $this->getJson('/api/dj-lounge/posts')
            ->assertOk()
            ->assertJsonPath('posts.0.authorName', 'DJ Nova')
            ->assertJsonPath('posts.0.body', 'First drop from the database lounge.')
            ->assertJsonPath('posts.0.likes', 0);
    }

    public function test_guest_cannot_create_post(): void
    {
        $this->postJson('/api/dj-lounge/posts', [
            'body' => 'No session no post.',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_post(): void
    {
        $user = User::create([
            'name' => 'DJ Pulse',
            'email' => 'pulse@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->postJson('/api/dj-lounge/posts', [
                'body' => 'Testing the real DJLounge feed.',
            ])
            ->assertCreated()
            ->assertJsonPath('post.authorName', 'DJ Pulse')
            ->assertJsonPath('post.body', 'Testing the real DJLounge feed.');

        $this->assertDatabaseHas('dj_lounge_posts', [
            'user_id' => $user->id,
            'body' => 'Testing the real DJLounge feed.',
        ]);
    }

    public function test_authenticated_user_can_toggle_social_actions(): void
    {
        $author = User::create([
            'name' => 'DJ Author',
            'email' => 'author@example.com',
            'password' => 'password',
        ]);
        $fan = User::create([
            'name' => 'Battle Fan',
            'email' => 'fan@example.com',
            'password' => 'password',
        ]);
        $post = DjLoungePost::create([
            'user_id' => $author->id,
            'body' => 'React to this.',
            'published_at' => now(),
        ]);

        $this->actingAs($fan)
            ->postJson("/api/dj-lounge/posts/{$post->id}/reaction")
            ->assertOk()
            ->assertJsonPath('liked', true)
            ->assertJsonPath('like_count', 1);

        $this->actingAs($fan)
            ->postJson("/api/dj-lounge/posts/{$post->id}/repost")
            ->assertOk()
            ->assertJsonPath('reposted', true)
            ->assertJsonPath('repost_count', 1);

        $this->actingAs($fan)
            ->postJson("/api/dj-lounge/posts/{$post->id}/bookmark")
            ->assertOk()
            ->assertJsonPath('bookmarked', true)
            ->assertJsonPath('bookmark_count', 1);
    }
}
