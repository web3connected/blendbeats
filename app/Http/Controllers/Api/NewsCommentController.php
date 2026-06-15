<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NewsCommentController extends Controller
{
    public function index(Post $post): JsonResponse
    {
        $this->abortUnlessPublicNews($post);

        $comments = $post->approvedComments()
            ->whereNull('parent_id')
            ->with([
                'user:id,name,email,avatar,use_gravatar,is_gravatar',
                'replies' => fn ($query) => $query
                    ->approved()
                    ->with('user:id,name,email,avatar,use_gravatar,is_gravatar')
                    ->oldest(),
            ])
            ->oldest()
            ->get();

        return response()->json([
            'comments' => $comments->map(fn (Comment $comment): array => $this->commentPayload($comment))->values(),
        ]);
    }

    public function store(Request $request, Post $post): JsonResponse
    {
        $this->abortUnlessPublicNews($post);

        $attributes = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        $parentId = $attributes['parent_id'] ?? null;

        if ($parentId) {
            $parent = Comment::query()
                ->where('post_id', $post->id)
                ->whereNull('parent_id')
                ->where('status', Comment::STATUS_APPROVED)
                ->findOrFail($parentId);

            $parentId = $parent->id;
        }

        $user = $request->user();
        $comment = $post->comments()->create([
            'user_id' => $user?->id,
            'parent_id' => $parentId,
            'author_name' => $attributes['author_name'] ?? $user?->name ?? 'Guest',
            'content' => $attributes['content'],
            'status' => Comment::STATUS_PENDING,
        ]);

        return response()->json([
            'comment' => $this->commentPayload($comment->load('user:id,name,email,avatar,use_gravatar,is_gravatar')),
            'message' => 'Comment submitted for review.',
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorizeCommentOwner($request, $comment);

        $attributes = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $comment->update([
            'content' => $attributes['content'],
            'status' => Comment::STATUS_PENDING,
            'approved_at' => null,
        ]);

        return response()->json([
            'comment' => $this->commentPayload($comment->refresh()->load('user:id,name,email,avatar,use_gravatar,is_gravatar')),
            'message' => 'Comment updated and submitted for review.',
        ]);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->authorizeCommentOwner($request, $comment);

        $comment->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }

    private function abortUnlessPublicNews(Post $post): void
    {
        abort_unless($post->isNews() && $post->isPublished(), 404);
    }

    private function authorizeCommentOwner(Request $request, Comment $comment): void
    {
        $user = $request->user();

        abort_unless($user && $comment->user_id && (int) $comment->user_id === (int) $user->id, 403);

        $this->abortUnlessPublicNews($comment->post);
    }

    private function commentPayload(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'parent_id' => $comment->parent_id,
            'author_name' => $comment->user?->name ?? $comment->author_name ?? 'Guest',
            'content' => $comment->content,
            'status' => $comment->status,
            'created_at' => $comment->created_at?->toISOString(),
            'updated_at' => $comment->updated_at?->toISOString(),
            'user' => $comment->user ? [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'avatar_url' => $comment->user->getAvatarUrl(),
            ] : null,
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn (Comment $reply): array => $this->commentPayload($reply))->values()
                : [],
        ];
    }
}
