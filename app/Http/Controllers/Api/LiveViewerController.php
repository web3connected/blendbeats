<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\LiveStreamViewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LiveViewerController extends Controller
{
    private const ACTIVE_WINDOW_SECONDS = 30;

    public function store(Request $request, LiveStream $liveStream): JsonResponse
    {
        $attributes = $request->validate([
            'viewer_id' => ['required', 'uuid'],
        ]);

        if ($liveStream->status !== LiveStream::STATUS_LIVE) {
            return response()->json([
                'message' => 'This live stream has ended.',
            ], Response::HTTP_CONFLICT);
        }

        $viewerHash = $this->viewerHash($attributes['viewer_id']);
        $user = $request->user();

        if ($user) {
            $viewer = LiveStreamViewer::query()
                ->where('live_stream_id', $liveStream->id)
                ->where('user_id', $user->id)
                ->latest('last_seen_at')
                ->first();

            if ($viewer) {
                $viewer->update([
                    'viewer_hash' => $viewerHash,
                    'display_name' => $user->name,
                    'last_seen_at' => now(),
                ]);

                LiveStreamViewer::query()
                    ->where('live_stream_id', $liveStream->id)
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $viewer->id)
                    ->delete();
            } else {
                LiveStreamViewer::query()->create([
                    'live_stream_id' => $liveStream->id,
                    'user_id' => $user->id,
                    'viewer_hash' => $viewerHash,
                    'display_name' => $user->name,
                    'last_seen_at' => now(),
                ]);
            }
        } else {
        LiveStreamViewer::query()->updateOrCreate(
            [
                'live_stream_id' => $liveStream->id,
                'viewer_hash' => $viewerHash,
            ],
            [
                'user_id' => $user?->id,
                'display_name' => $user?->name ?: 'Guest '.strtoupper(substr($viewerHash, 0, 4)),
                'last_seen_at' => now(),
            ],
        );
        }

        return response()->json($this->presencePayload($liveStream));
    }

    public function destroy(Request $request, LiveStream $liveStream): JsonResponse
    {
        $attributes = $request->validate([
            'viewer_id' => ['required', 'uuid'],
        ]);

        LiveStreamViewer::query()
            ->where('live_stream_id', $liveStream->id)
            ->where('viewer_hash', $this->viewerHash($attributes['viewer_id']))
            ->delete();

        return response()->json($this->presencePayload($liveStream));
    }

    private function presencePayload(LiveStream $liveStream): array
    {
        $cutoff = now()->subSeconds(self::ACTIVE_WINDOW_SECONDS);

        LiveStreamViewer::query()
            ->where('live_stream_id', $liveStream->id)
            ->where('last_seen_at', '<', $cutoff)
            ->delete();

        $viewers = LiveStreamViewer::query()
            ->where('live_stream_id', $liveStream->id)
            ->where('last_seen_at', '>=', $cutoff)
            ->latest('last_seen_at')
            ->get(['user_id', 'viewer_hash', 'display_name'])
            ->unique(fn (LiveStreamViewer $viewer): string => $viewer->user_id
                ? "user:{$viewer->user_id}"
                : "guest:{$viewer->viewer_hash}")
            ->sortBy('display_name')
            ->values();

        return [
            'count' => $viewers->count(),
            'viewers' => $viewers->map(fn (LiveStreamViewer $viewer): array => [
                'user_id' => $viewer->user_id,
                'name' => $viewer->display_name,
                'is_guest' => $viewer->user_id === null,
            ])->values(),
        ];
    }

    private function viewerHash(string $viewerId): string
    {
        return hash('sha256', $viewerId);
    }
}
