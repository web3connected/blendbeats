<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DjLoungeBookmark;
use App\Models\DjLoungeComment;
use App\Models\DjLoungePost;
use App\Models\DjLoungeReaction;
use App\Models\DjLoungeReport;
use App\Models\DjLoungeRepost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DjLoungeController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::guard('web')->user();

        $posts = DjLoungePost::query()
            ->with('user:id,name,email,avatar')
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->latest('published_at')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'posts' => $posts->map(fn (DjLoungePost $post): array => $this->postPayload($post, $user?->id)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'body' => ['required', 'string', 'max:500'],
            'type' => ['nullable', Rule::in(['text', 'mix_update', 'battle_callout', 'question'])],
            'visibility' => ['nullable', Rule::in(['public', 'followers', 'private'])],
            'genre' => ['nullable', 'string', 'max:80'],
            'media_title' => ['nullable', 'string', 'max:255'],
            'media_url' => ['nullable', 'url', 'max:255'],
            'media_meta' => ['nullable', 'string', 'max:255'],
        ]);

        $post = DjLoungePost::create([
            ...$attributes,
            'user_id' => Auth::id(),
            'type' => $attributes['type'] ?? 'text',
            'visibility' => $attributes['visibility'] ?? 'public',
            'published_at' => now(),
        ])->load('user:id,name,email,avatar');

        return response()->json([
            'post' => $this->postPayload($post, Auth::id()),
        ], 201);
    }

    public function comments(DjLoungePost $post): JsonResponse
    {
        $comments = $post->comments()
            ->with('user:id,name,email,avatar')
            ->where('status', 'visible')
            ->whereNull('parent_id')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'comments' => $comments->map(fn (DjLoungeComment $comment): array => $this->commentPayload($comment)),
        ]);
    }

    public function storeComment(Request $request, DjLoungePost $post): JsonResponse
    {
        $attributes = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'parent_id' => [
                'nullable',
                Rule::exists('dj_lounge_comments', 'id')->where('dj_lounge_post_id', $post->id),
            ],
        ]);

        $comment = DB::transaction(function () use ($attributes, $post): DjLoungeComment {
            $comment = $post->comments()->create([
                'user_id' => Auth::id(),
                'parent_id' => $attributes['parent_id'] ?? null,
                'body' => $attributes['body'],
            ]);

            $post->increment('comment_count');

            if ($comment->parent_id) {
                DjLoungeComment::whereKey($comment->parent_id)->increment('reply_count');
            }

            return $comment;
        });

        return response()->json([
            'comment' => $this->commentPayload($comment->load('user:id,name,email,avatar')),
        ], 201);
    }

    public function toggleReaction(DjLoungePost $post): JsonResponse
    {
        $liked = DB::transaction(function () use ($post): bool {
            $reaction = $post->reactions()
                ->where('user_id', Auth::id())
                ->where('type', 'like')
                ->first();

            if ($reaction) {
                $reaction->delete();
                $post->decrement('like_count');

                return false;
            }

            $post->reactions()->create([
                'user_id' => Auth::id(),
                'type' => 'like',
            ]);
            $post->increment('like_count');

            return true;
        });

        return response()->json([
            'liked' => $liked,
            'like_count' => $post->refresh()->like_count,
        ]);
    }

    public function toggleRepost(Request $request, DjLoungePost $post): JsonResponse
    {
        $attributes = $request->validate([
            'body' => ['nullable', 'string', 'max:280'],
        ]);

        $reposted = DB::transaction(function () use ($attributes, $post): bool {
            $repost = $post->reposts()->where('user_id', Auth::id())->first();

            if ($repost) {
                $repost->delete();
                $post->decrement('repost_count');

                return false;
            }

            $post->reposts()->create([
                'user_id' => Auth::id(),
                'body' => $attributes['body'] ?? null,
            ]);
            $post->increment('repost_count');

            return true;
        });

        return response()->json([
            'reposted' => $reposted,
            'repost_count' => $post->refresh()->repost_count,
        ]);
    }

    public function toggleBookmark(DjLoungePost $post): JsonResponse
    {
        $bookmarked = DB::transaction(function () use ($post): bool {
            $bookmark = $post->bookmarks()->where('user_id', Auth::id())->first();

            if ($bookmark) {
                $bookmark->delete();
                $post->decrement('bookmark_count');

                return false;
            }

            $post->bookmarks()->create([
                'user_id' => Auth::id(),
            ]);
            $post->increment('bookmark_count');

            return true;
        });

        return response()->json([
            'bookmarked' => $bookmarked,
            'bookmark_count' => $post->refresh()->bookmark_count,
        ]);
    }

    public function report(Request $request, DjLoungePost $post): JsonResponse
    {
        $attributes = $request->validate([
            'reason' => ['required', Rule::in(['spam', 'harassment', 'copyright', 'explicit', 'impersonation', 'other'])],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        DjLoungeReport::create([
            'reporter_user_id' => Auth::id(),
            'reportable_type' => $post::class,
            'reportable_id' => $post->id,
            'reason' => $attributes['reason'],
            'details' => $attributes['details'] ?? null,
        ]);

        return response()->json(['ok' => true], 201);
    }

    private function postPayload(DjLoungePost $post, ?int $userId): array
    {
        return [
            'id' => (string) $post->id,
            'authorName' => $post->user->name,
            'handle' => '@'.str($post->user->name)->slug(''),
            'avatarInitial' => str($post->user->name)->substr(0, 1)->upper()->toString(),
            'role' => 'Community DJ',
            'timestamp' => $post->published_at?->diffForHumans() ?? $post->created_at->diffForHumans(),
            'body' => $post->body,
            'genre' => $post->genre ?? 'Community',
            'mediaTitle' => $post->media_title,
            'mediaUrl' => $post->media_url,
            'mediaMeta' => $post->media_meta,
            'likes' => $post->like_count,
            'comments' => $post->comment_count,
            'reposts' => $post->repost_count,
            'bookmarks' => $post->bookmark_count,
            'isLive' => $post->is_live,
            'liked' => $userId ? $this->hasReaction($post, $userId) : false,
            'reposted' => $userId ? $this->hasRepost($post, $userId) : false,
            'bookmarked' => $userId ? $this->hasBookmark($post, $userId) : false,
        ];
    }

    private function commentPayload(DjLoungeComment $comment): array
    {
        return [
            'id' => (string) $comment->id,
            'postId' => (string) $comment->dj_lounge_post_id,
            'parentId' => $comment->parent_id ? (string) $comment->parent_id : null,
            'authorName' => $comment->user->name,
            'avatarInitial' => str($comment->user->name)->substr(0, 1)->upper()->toString(),
            'body' => $comment->body,
            'likes' => $comment->like_count,
            'replies' => $comment->reply_count,
            'timestamp' => $comment->created_at->diffForHumans(),
        ];
    }

    private function hasReaction(DjLoungePost $post, int $userId): bool
    {
        return DjLoungeReaction::where('user_id', $userId)
            ->where('reactable_type', $post::class)
            ->where('reactable_id', $post->id)
            ->where('type', 'like')
            ->exists();
    }

    private function hasRepost(DjLoungePost $post, int $userId): bool
    {
        return DjLoungeRepost::where('user_id', $userId)
            ->where('dj_lounge_post_id', $post->id)
            ->exists();
    }

    private function hasBookmark(DjLoungePost $post, int $userId): bool
    {
        return DjLoungeBookmark::where('user_id', $userId)
            ->where('dj_lounge_post_id', $post->id)
            ->exists();
    }
}
