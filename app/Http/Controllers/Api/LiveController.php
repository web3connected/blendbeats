<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\SerializesLiveStreams;
use App\Http\Controllers\Controller;
use App\Services\Live\LiveService;
use Illuminate\Http\JsonResponse;

class LiveController extends Controller
{
    use SerializesLiveStreams;

    public function index(LiveService $live): JsonResponse
    {
        return response()->json([
            'streams' => $live->activeStreams()
                ->map(fn ($stream): array => $this->liveStreamPayload($stream))
                ->values(),
        ]);
    }

    public function show(string $username, LiveService $live): JsonResponse
    {
        $channel = $live->channelBySlug($username);

        abort_unless($channel, 404);

        return response()->json([
            'channel' => $this->liveChannelPayload(
                $channel,
                $live->activeStreamForChannel($channel),
            ),
        ]);
    }
}
