<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class DjLoungeController extends Controller
{
    private const POST_MODEL_TYPE = 'App\\Models\\DjLoungePost';

    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('dj_lounge_posts')) {
            return response()->json(['posts' => []]);
        }

        $userId = $request->user()?->id;

        $posts = DB::table('dj_lounge_posts')
            ->join('users', 'users.id', '=', 'dj_lounge_posts.user_id')
            ->select([
                'dj_lounge_posts.*',
                'users.name as author_name',
            ])
            ->where('dj_lounge_posts.status', 'published')
            ->where('dj_lounge_posts.visibility', 'public')
            ->whereNull('dj_lounge_posts.deleted_at')
            ->orderByDesc('dj_lounge_posts.published_at')
            ->orderByDesc('dj_lounge_posts.created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'posts' => $posts->map(fn ($post): array => $this->postPayload($post, $userId))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, Response::HTTP_UNAUTHORIZED);
        abort_unless(Schema::hasTable('dj_lounge_posts'), Response::HTTP_SERVICE_UNAVAILABLE, 'DJLounge is not available right now.');

        $attributes = $request->validate([
            'body' => ['required', 'string', 'max:280'],
        ]);

        $postId = DB::table('dj_lounge_posts')->insertGetId([
            'user_id' => $user->id,
            'body' => $attributes['body'],
            'type' => 'text',
            'status' => 'published',
            'visibility' => 'public',
            'like_count' => 0,
            'comment_count' => 0,
            'repost_count' => 0,
            'bookmark_count' => 0,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post = DB::table('dj_lounge_posts')
            ->join('users', 'users.id', '=', 'dj_lounge_posts.user_id')
            ->select(['dj_lounge_posts.*', 'users.name as author_name'])
            ->where('dj_lounge_posts.id', $postId)
            ->first();

        return response()->json([
            'post' => $this->postPayload($post, $user->id),
        ], Response::HTTP_CREATED);
    }

    public function toggleReaction(Request $request, string $post): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        $post = $this->publicPostOrFail($post);
        abort_unless(Schema::hasTable('dj_lounge_reactions'), Response::HTTP_SERVICE_UNAVAILABLE, 'DJLounge reactions are not available right now.');

        $existing = DB::table('dj_lounge_reactions')
            ->where('user_id', $userId)
            ->where('reactable_type', self::POST_MODEL_TYPE)
            ->where('reactable_id', $post->id)
            ->first();

        if ($existing) {
            DB::table('dj_lounge_reactions')->where('id', $existing->id)->delete();
            DB::table('dj_lounge_posts')->where('id', $post->id)->where('like_count', '>', 0)->decrement('like_count');
            $liked = false;
        } else {
            DB::table('dj_lounge_reactions')->insert([
                'user_id' => $userId,
                'reactable_type' => self::POST_MODEL_TYPE,
                'reactable_id' => $post->id,
                'type' => 'like',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('dj_lounge_posts')->where('id', $post->id)->increment('like_count');
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'like_count' => (int) DB::table('dj_lounge_posts')->where('id', $post->id)->value('like_count'),
        ]);
    }

    public function toggleRepost(Request $request, string $post): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        $post = $this->publicPostOrFail($post);
        abort_unless(Schema::hasTable('dj_lounge_reposts'), Response::HTTP_SERVICE_UNAVAILABLE, 'DJLounge reposts are not available right now.');

        $existing = DB::table('dj_lounge_reposts')
            ->where('user_id', $userId)
            ->where('dj_lounge_post_id', $post->id)
            ->first();

        if ($existing) {
            DB::table('dj_lounge_reposts')->where('id', $existing->id)->delete();
            DB::table('dj_lounge_posts')->where('id', $post->id)->where('repost_count', '>', 0)->decrement('repost_count');
            $reposted = false;
        } else {
            DB::table('dj_lounge_reposts')->insert([
                'user_id' => $userId,
                'dj_lounge_post_id' => $post->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('dj_lounge_posts')->where('id', $post->id)->increment('repost_count');
            $reposted = true;
        }

        return response()->json([
            'reposted' => $reposted,
            'repost_count' => (int) DB::table('dj_lounge_posts')->where('id', $post->id)->value('repost_count'),
        ]);
    }

    public function toggleBookmark(Request $request, string $post): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        $post = $this->publicPostOrFail($post);
        abort_unless(Schema::hasTable('dj_lounge_bookmarks'), Response::HTTP_SERVICE_UNAVAILABLE, 'DJLounge bookmarks are not available right now.');

        $existing = DB::table('dj_lounge_bookmarks')
            ->where('user_id', $userId)
            ->where('dj_lounge_post_id', $post->id)
            ->first();

        if ($existing) {
            DB::table('dj_lounge_bookmarks')->where('id', $existing->id)->delete();
            DB::table('dj_lounge_posts')->where('id', $post->id)->where('bookmark_count', '>', 0)->decrement('bookmark_count');
            $bookmarked = false;
        } else {
            DB::table('dj_lounge_bookmarks')->insert([
                'user_id' => $userId,
                'dj_lounge_post_id' => $post->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('dj_lounge_posts')->where('id', $post->id)->increment('bookmark_count');
            $bookmarked = true;
        }

        return response()->json([
            'bookmarked' => $bookmarked,
            'bookmark_count' => (int) DB::table('dj_lounge_posts')->where('id', $post->id)->value('bookmark_count'),
        ]);
    }

    private function postPayload(object $post, ?int $userId): array
    {
        return [
            'id' => (string) $post->id,
            'authorName' => $post->author_name ?? 'BlendBeats DJ',
            'handle' => '@'.str($post->author_name ?? 'dj')->slug(),
            'avatarInitial' => strtoupper(substr((string) ($post->author_name ?? 'D'), 0, 1)),
            'role' => $post->genre ?: 'DJ',
            'timestamp' => Carbon::parse($post->published_at ?: $post->created_at)->diffForHumans(),
            'body' => $post->body,
            'genre' => $post->genre ?? 'Open Format',
            'mediaTitle' => $post->media_title,
            'mediaUrl' => $post->media_url,
            'mediaMeta' => $post->media_meta,
            'likes' => (int) $post->like_count,
            'comments' => (int) $post->comment_count,
            'reposts' => (int) $post->repost_count,
            'bookmarks' => (int) $post->bookmark_count,
            'isLive' => false,
            'liked' => $userId ? $this->hasReaction((int) $post->id, $userId) : false,
            'reposted' => $userId ? $this->hasRepost((int) $post->id, $userId) : false,
            'bookmarked' => $userId ? $this->hasBookmark((int) $post->id, $userId) : false,
        ];
    }

    private function authenticatedUserId(Request $request): int
    {
        $userId = $request->user()?->id;
        abort_unless($userId, Response::HTTP_UNAUTHORIZED);

        return (int) $userId;
    }

    private function publicPostOrFail(string $postId): object
    {
        abort_unless(Schema::hasTable('dj_lounge_posts'), Response::HTTP_SERVICE_UNAVAILABLE, 'DJLounge is not available right now.');

        $post = DB::table('dj_lounge_posts')
            ->where('id', $postId)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->whereNull('deleted_at')
            ->first();

        abort_unless($post, Response::HTTP_NOT_FOUND);

        return $post;
    }

    private function hasReaction(int $postId, int $userId): bool
    {
        return Schema::hasTable('dj_lounge_reactions')
            && DB::table('dj_lounge_reactions')
                ->where('user_id', $userId)
                ->where('reactable_type', self::POST_MODEL_TYPE)
                ->where('reactable_id', $postId)
                ->exists();
    }

    private function hasRepost(int $postId, int $userId): bool
    {
        return Schema::hasTable('dj_lounge_reposts')
            && DB::table('dj_lounge_reposts')
                ->where('user_id', $userId)
                ->where('dj_lounge_post_id', $postId)
                ->exists();
    }

    private function hasBookmark(int $postId, int $userId): bool
    {
        return Schema::hasTable('dj_lounge_bookmarks')
            && DB::table('dj_lounge_bookmarks')
                ->where('user_id', $userId)
                ->where('dj_lounge_post_id', $postId)
                ->exists();
    }
}
