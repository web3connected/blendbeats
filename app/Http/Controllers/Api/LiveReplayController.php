<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\SerializesLiveStreams;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveReplayController extends Controller
{
    use SerializesLiveStreams;

    public function show(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->ensureReplay($liveStream);
        $liveStream->load(['liveChannel', 'user.djProfile', 'comments.user'])->loadCount('likes');

        return response()->json([
            'stream' => $this->liveStreamPayload($liveStream),
            'liked' => $request->user() ? $liveStream->likes()->where('user_id', $request->user()->id)->exists() : false,
            'comments' => $liveStream->comments->map(fn ($comment): array => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at->toIso8601String(),
                'user' => ['id' => $comment->user->id, 'name' => $comment->user->name],
            ])->values(),
        ]);
    }

    public function view(LiveStream $liveStream): JsonResponse
    {
        $this->ensureReplay($liveStream);
        $liveStream->increment('views_count');

        return response()->json(['views_count' => $liveStream->fresh()->views_count]);
    }

    public function like(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->ensureReplay($liveStream);
        $like = $liveStream->likes()->where('user_id', $request->user()->id)->first();
        $liked = ! $like;
        $like ? $like->delete() : $liveStream->likes()->create(['user_id' => $request->user()->id]);

        return response()->json(['liked' => $liked, 'likes_count' => $liveStream->likes()->count()]);
    }

    public function comment(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->ensureReplay($liveStream);
        $attributes = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $comment = $liveStream->comments()->create(['user_id' => $request->user()->id, 'body' => trim($attributes['body'])]);

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at->toIso8601String(),
                'user' => ['id' => $request->user()->id, 'name' => $request->user()->name],
            ],
        ], 201);
    }

    private function ensureReplay(LiveStream $liveStream): void
    {
        abort_unless($liveStream->status === LiveStream::STATUS_ENDED && $liveStream->recording_enabled, 404);
    }
}
