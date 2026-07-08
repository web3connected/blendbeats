<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\LiveChannel;
use App\Models\LiveStream;

trait SerializesLiveStreams
{
    private function liveChannelPayload(LiveChannel $channel, ?LiveStream $activeStream = null): array
    {
        $user = $channel->user;
        $profile = $user?->djProfile;

        return [
            'id' => $channel->id,
            'username_slug' => $channel->username_slug,
            'title' => $channel->title,
            'description' => $channel->description,
            'is_enabled' => $channel->is_enabled,
            'dj' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'dj_name' => $profile?->dj_name,
                'handle' => $profile?->handle,
            ],
            'active_stream' => $activeStream ? $this->liveStreamPayload($activeStream) : null,
        ];
    }

    private function liveStreamPayload(LiveStream $stream): array
    {
        return [
            'id' => $stream->id,
            'live_channel_id' => $stream->live_channel_id,
            'user_id' => $stream->user_id,
            'agora_channel_name' => $stream->agora_channel_name,
            'title' => $stream->title,
            'status' => $stream->status,
            'max_duration_minutes' => $stream->max_duration_minutes,
            'started_at' => $stream->started_at?->toIso8601String(),
            'ended_at' => $stream->ended_at?->toIso8601String(),
            'recording_enabled' => $stream->recording_enabled,
            'recording_status' => $stream->recording_status,
            'recording_started_at' => $stream->recording_started_at?->toIso8601String(),
            'recording_ended_at' => $stream->recording_ended_at?->toIso8601String(),
            'recording_storage_path' => $stream->recording_storage_path,
            'channel' => $stream->relationLoaded('liveChannel') && $stream->liveChannel
                ? [
                    'id' => $stream->liveChannel->id,
                    'username_slug' => $stream->liveChannel->username_slug,
                    'title' => $stream->liveChannel->title,
                ]
                : null,
            'dj' => $stream->relationLoaded('user') && $stream->user
                ? [
                    'id' => $stream->user->id,
                    'name' => $stream->user->name,
                    'dj_name' => $stream->user->djProfile?->dj_name,
                    'handle' => $stream->user->djProfile?->handle,
                ]
                : null,
        ];
    }
}
