<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
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
    private const COMMENT_MODEL_TYPE = 'App\\Models\\DjLoungeComment';

    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('dj_lounge_posts')) {
            return response()->json([
                'posts' => [],
                'stats' => $this->emptyStats(),
            ]);
        }

        $userId = $request->user()?->id;

        $posts = DB::table('dj_lounge_posts')
            ->join('users', 'users.id', '=', 'dj_lounge_posts.user_id')
            ->select([
                'dj_lounge_posts.*',
                'users.name as author_name',
                'users.email as author_email',
                'users.avatar as author_avatar',
                'users.is_gravatar as author_is_gravatar',
                'users.use_gravatar as author_use_gravatar',
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
            'stats' => $this->feedStats(),
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
            ->select([
                'dj_lounge_posts.*',
                'users.name as author_name',
                'users.email as author_email',
                'users.avatar as author_avatar',
                'users.is_gravatar as author_is_gravatar',
                'users.use_gravatar as author_use_gravatar',
            ])
            ->where('dj_lounge_posts.id', $postId)
            ->first();

        return response()->json([
            'post' => $this->postPayload($post, $user->id),
        ], Response::HTTP_CREATED);
    }

    public function storeReply(Request $request, string $post): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, Response::HTTP_UNAUTHORIZED);
        $post = $this->publicPostOrFail($post);
        abort_unless(Schema::hasTable('dj_lounge_comments'), Response::HTTP_SERVICE_UNAVAILABLE, 'DJLounge replies are not available right now.');

        $attributes = $request->validate([
            'body' => ['required', 'string', 'max:500'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = null;

        if (! empty($attributes['parent_id'])) {
            $parent = DB::table('dj_lounge_comments')
                ->where('id', $attributes['parent_id'])
                ->where('dj_lounge_post_id', $post->id)
                ->where('status', 'visible')
                ->whereNull('deleted_at')
                ->first();

            abort_unless($parent, Response::HTTP_NOT_FOUND);
            abort_if($parent->parent_id, Response::HTTP_UNPROCESSABLE_ENTITY, 'Replies can only go one level deep.');
        }

        $replyId = DB::transaction(function () use ($attributes, $parent, $post, $user): int {
            $replyId = DB::table('dj_lounge_comments')->insertGetId([
                'dj_lounge_post_id' => $post->id,
                'user_id' => $user->id,
                'parent_id' => $parent?->id,
                'body' => $attributes['body'],
                'status' => 'visible',
                'like_count' => 0,
                'reply_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('dj_lounge_posts')->where('id', $post->id)->increment('comment_count');

            if ($parent) {
                DB::table('dj_lounge_comments')->where('id', $parent->id)->increment('reply_count');
            }

            return $replyId;
        });

        $reply = $this->replyQuery()
            ->where('dj_lounge_comments.id', $replyId)
            ->first();

        return response()->json([
            'reply' => $this->replyPayload($reply, []),
            'comment_count' => (int) DB::table('dj_lounge_posts')->where('id', $post->id)->value('comment_count'),
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
        $replies = $this->postReplies((int) $post->id);

        return [
            'id' => (string) $post->id,
            'authorName' => $post->author_name ?? 'BlendBeats DJ',
            'handle' => '@'.str($post->author_name ?? 'dj')->slug(),
            'avatarInitial' => strtoupper(substr((string) ($post->author_name ?? 'D'), 0, 1)),
            'avatarUrl' => $this->authorAvatarUrl($post),
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
            'replies' => $replies,
        ];
    }

    private function feedStats(): array
    {
        $baseQuery = DB::table('dj_lounge_posts')
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->whereNull('deleted_at');

        return [
            'postsToday' => (int) (clone $baseQuery)->whereDate('created_at', today())->count(),
            'djsOnline' => (int) (clone $baseQuery)->where('created_at', '>=', now()->subMinutes(15))->distinct('user_id')->count('user_id'),
            'liveThreads' => (int) (clone $baseQuery)->where('type', 'battle_callout')->where('created_at', '>=', now()->subHours(2))->count(),
        ];
    }

    private function emptyStats(): array
    {
        return [
            'postsToday' => 0,
            'djsOnline' => 0,
            'liveThreads' => 0,
        ];
    }

    private function postReplies(int $postId): array
    {
        if (! Schema::hasTable('dj_lounge_comments')) {
            return [];
        }

        $comments = $this->replyQuery()
            ->where('dj_lounge_comments.dj_lounge_post_id', $postId)
            ->whereNull('dj_lounge_comments.parent_id')
            ->orderBy('dj_lounge_comments.created_at')
            ->limit(8)
            ->get();

        if ($comments->isEmpty()) {
            return [];
        }

        $children = $this->replyQuery()
            ->where('dj_lounge_comments.dj_lounge_post_id', $postId)
            ->whereIn('dj_lounge_comments.parent_id', $comments->pluck('id'))
            ->orderBy('dj_lounge_comments.created_at')
            ->get()
            ->groupBy('parent_id');

        return $comments
            ->map(fn ($comment): array => $this->replyPayload($comment, $children->get($comment->id, collect())->all()))
            ->values()
            ->all();
    }

    private function replyQuery(): Builder
    {
        return DB::table('dj_lounge_comments')
            ->join('users', 'users.id', '=', 'dj_lounge_comments.user_id')
            ->select([
                'dj_lounge_comments.*',
                'users.name as author_name',
                'users.email as author_email',
                'users.avatar as author_avatar',
                'users.is_gravatar as author_is_gravatar',
                'users.use_gravatar as author_use_gravatar',
            ])
            ->where('dj_lounge_comments.status', 'visible')
            ->whereNull('dj_lounge_comments.deleted_at');
    }

    private function replyPayload(object $reply, array $children): array
    {
        return [
            'id' => (string) $reply->id,
            'postId' => (string) $reply->dj_lounge_post_id,
            'parentId' => $reply->parent_id ? (string) $reply->parent_id : null,
            'authorName' => $reply->author_name ?? 'BlendBeats DJ',
            'handle' => '@'.str($reply->author_name ?? 'dj')->slug(),
            'avatarInitial' => strtoupper(substr((string) ($reply->author_name ?? 'D'), 0, 1)),
            'avatarUrl' => $this->authorAvatarUrl($reply),
            'timestamp' => Carbon::parse($reply->created_at)->diffForHumans(),
            'body' => $reply->body,
            'likes' => (int) $reply->like_count,
            'replyCount' => (int) $reply->reply_count,
            'replies' => collect($children)
                ->map(fn ($child): array => $this->replyPayload($child, []))
                ->values()
                ->all(),
        ];
    }

    private function authorAvatarUrl(object $author): string
    {
        $user = new User();
        $user->forceFill([
            'name' => $author->author_name ?? 'BlendBeats DJ',
            'email' => $author->author_email ?? '',
            'avatar' => $author->author_avatar ?? null,
            'is_gravatar' => (bool) ($author->author_is_gravatar ?? false),
            'use_gravatar' => (bool) ($author->author_use_gravatar ?? false),
        ]);

        return $user->getAvatarUrl(128);
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
