<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\SerializesLiveStreams;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Services\Live\AgoraService;
use App\Services\Live\LiveService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LiveStudioController extends Controller
{
    use SerializesLiveStreams;

    public function show(Request $request, LiveService $live): JsonResponse
    {
        $state = $live->studioState($request->user());

        return response()->json([
            'can_go_live' => $state['can_go_live'],
            'limits' => $state['limits'],
            'monthly_usage' => $state['monthly_usage'],
            'channel' => $state['channel'] ? $this->liveChannelPayload($state['channel'], $state['active_stream']) : null,
            'active_stream' => $state['active_stream'] ? $this->liveStreamPayload($state['active_stream']) : null,
        ]);
    }

    public function start(Request $request, LiveService $live, AgoraService $agora): JsonResponse
    {
        $attributes = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'recording_enabled' => ['nullable', 'boolean'],
        ]);

        try {
            // Validate Agora before creating a live stream so a configuration
            // problem cannot leave the DJ with an unusable active session.
            $agora->assertConfigured();
            $stream = $live->start(
                $request->user(),
                $attributes['title'] ?? null,
                (bool) ($attributes['recording_enabled'] ?? false),
            );
            $token = $agora->tokenForStream($stream, 'host');
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (ConflictHttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Agora is not configured correctly.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json([
            'stream' => $this->liveStreamPayload($stream),
            'token' => $token,
        ], Response::HTTP_CREATED);
    }

    public function end(Request $request, LiveService $live): JsonResponse
    {
        $stream = $live->end($request->user());

        return response()->json([
            'ended' => (bool) $stream,
            'stream' => $stream ? $this->liveStreamPayload($stream) : null,
        ]);
    }

    public function recording(Request $request, LiveStream $liveStream): JsonResponse
    {
        abort_unless($liveStream->user_id === $request->user()->id && $liveStream->recording_enabled, 403);

        $request->validate([
            'recording' => ['required', 'file', 'mimetypes:video/webm,video/mp4,application/octet-stream', 'max:1048576'],
        ]);

        $extension = $request->file('recording')->guessExtension() ?: 'webm';
        $path = $request->file('recording')->storeAs('live-replays/'.$liveStream->id, 'replay.'.$extension, 'public');
        abort_unless($path, 500, 'Unable to save the replay recording.');

        $liveStream->forceFill([
            'recording_storage_path' => $path,
            'recording_status' => 'ready',
            'recording_started_at' => $liveStream->recording_started_at ?: $liveStream->started_at,
            'recording_ended_at' => now(),
        ])->save();

        return response()->json(['stream' => $this->liveStreamPayload($liveStream->refresh())]);
    }
}
