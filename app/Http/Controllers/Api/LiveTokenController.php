<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Services\Live\AgoraService;
use App\Services\Live\LiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class LiveTokenController extends Controller
{
    public function store(Request $request, LiveService $live, AgoraService $agora): JsonResponse
    {
        $attributes = $request->validate([
            'role' => ['required', Rule::in(['host', 'audience'])],
            'live_stream_id' => ['nullable', 'integer'],
            'username_slug' => ['nullable', 'string', 'max:255'],
        ]);

        $stream = $this->resolveStream($request, $attributes, $live);

        abort_unless($stream, 404);

        if ($attributes['role'] === 'host') {
            abort_unless($request->user(), 401);
            abort_unless($stream->user_id === $request->user()->id, 403);
        }

        try {
            return response()->json($agora->tokenForStream($stream, $attributes['role']));
        } catch (InvalidArgumentException) {
            return response()->json([
                'message' => 'Agora is not configured correctly.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    private function resolveStream(Request $request, array $attributes, LiveService $live): ?LiveStream
    {
        if (! empty($attributes['live_stream_id'])) {
            return LiveStream::query()
                ->where('status', LiveStream::STATUS_LIVE)
                ->find($attributes['live_stream_id']);
        }

        if (! empty($attributes['username_slug'])) {
            $channel = $live->channelBySlug($attributes['username_slug']);

            return $channel ? $live->activeStreamForChannel($channel) : null;
        }

        if (($attributes['role'] ?? null) === 'host' && $request->user()) {
            return $live->activeStreamForUser($request->user());
        }

        return null;
    }
}
