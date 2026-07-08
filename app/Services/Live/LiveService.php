<?php

namespace App\Services\Live;

use App\Models\DjProfile;
use App\Models\LiveChannel;
use App\Models\LiveStream;
use App\Models\User;
use App\Services\MembershipTierService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LiveService
{
    public function __construct(
        private readonly MembershipTierService $membershipTiers,
    ) {
    }

    public function activeStreams(): Collection
    {
        return LiveStream::query()
            ->with(['liveChannel.user.djProfile', 'user.djProfile'])
            ->where('status', LiveStream::STATUS_LIVE)
            ->whereHas('liveChannel', fn ($query) => $query->where('is_enabled', true))
            ->latest('started_at')
            ->get();
    }

    public function channelBySlug(string $usernameSlug): ?LiveChannel
    {
        return LiveChannel::query()
            ->with(['user.djProfile', 'activeStream'])
            ->where('username_slug', $usernameSlug)
            ->first();
    }

    public function studioState(User $user): array
    {
        $channel = $user->liveChannel()
            ->with('activeStream')
            ->first();
        $limits = $this->membershipTiers->liveLimitsFor($user);
        $monthlyUsage = $this->monthlyStreamCount($user);

        return [
            'can_go_live' => $limits['can_go_live'] && $user->djProfile()->exists(),
            'limits' => $limits,
            'monthly_usage' => [
                'used' => $monthlyUsage,
                'limit' => $limits['monthly_stream_limit'],
                'remaining' => $limits['monthly_stream_limit'] === null
                    ? null
                    : max(0, (int) $limits['monthly_stream_limit'] - $monthlyUsage),
            ],
            'channel' => $channel,
            'active_stream' => $channel?->activeStream,
        ];
    }

    public function start(User $user, ?string $title = null, bool $recordingRequested = false): LiveStream
    {
        if (! $user->canGoLive()) {
            throw new AuthorizationException('Only paid DJ accounts can go live.');
        }

        return DB::transaction(function () use ($user, $title, $recordingRequested): LiveStream {
            $channel = $this->ensureChannel($user);
            $activeStream = $this->activeStreamForUser($user);

            if ($activeStream) {
                throw new ConflictHttpException('You already have an active live stream.');
            }

            $limits = $this->membershipTiers->liveLimitsFor($user);
            $monthlyLimit = $limits['monthly_stream_limit'];

            if ($monthlyLimit !== null && $this->monthlyStreamCount($user) >= (int) $monthlyLimit) {
                throw new AuthorizationException('You have reached your monthly live stream limit for your plan.');
            }

            $recordingEnabled = $recordingRequested && $limits['can_record_live_streams'];

            return LiveStream::query()->create([
                'live_channel_id' => $channel->id,
                'user_id' => $user->id,
                'agora_channel_name' => $this->agoraChannelName($channel),
                'title' => $title ?: $channel->title,
                'status' => LiveStream::STATUS_LIVE,
                'max_duration_minutes' => $limits['max_stream_minutes'],
                'started_at' => now(),
                'recording_enabled' => $recordingEnabled,
                'recording_status' => $recordingEnabled ? 'requested' : null,
            ])->load(['liveChannel.user.djProfile', 'user.djProfile']);
        });
    }

    public function end(User $user): ?LiveStream
    {
        $stream = $this->activeStreamForUser($user);

        if (! $stream) {
            return null;
        }

        $stream->forceFill([
            'status' => LiveStream::STATUS_ENDED,
            'ended_at' => now(),
        ])->save();

        return $stream->refresh()->load(['liveChannel.user.djProfile', 'user.djProfile']);
    }

    public function activeStreamForUser(User $user): ?LiveStream
    {
        return LiveStream::query()
            ->with(['liveChannel.user.djProfile', 'user.djProfile'])
            ->where('user_id', $user->id)
            ->where('status', LiveStream::STATUS_LIVE)
            ->latest('started_at')
            ->first();
    }

    public function activeStreamForChannel(LiveChannel $channel): ?LiveStream
    {
        return LiveStream::query()
            ->with(['liveChannel.user.djProfile', 'user.djProfile'])
            ->where('live_channel_id', $channel->id)
            ->where('status', LiveStream::STATUS_LIVE)
            ->latest('started_at')
            ->first();
    }

    public function monthlyStreamCount(User $user): int
    {
        return LiveStream::query()
            ->where('user_id', $user->id)
            ->whereBetween('started_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->count();
    }

    public function endExpiredStreams(): int
    {
        $now = now();
        $count = 0;

        LiveStream::query()
            ->where('status', LiveStream::STATUS_LIVE)
            ->whereNotNull('max_duration_minutes')
            ->whereNotNull('started_at')
            ->orderBy('id')
            ->chunkById(100, function (Collection $streams) use ($now, &$count): void {
                foreach ($streams as $stream) {
                    if ($stream->started_at->copy()->addMinutes((int) $stream->max_duration_minutes)->isFuture()) {
                        continue;
                    }

                    $stream->forceFill([
                        'status' => LiveStream::STATUS_ENDED,
                        'ended_at' => $now,
                    ])->save();

                    $count++;
                }
            });

        return $count;
    }

    public function ensureChannel(User $user): LiveChannel
    {
        /** @var DjProfile|null $profile */
        $profile = $user->djProfile()->first();

        if (! $profile) {
            throw new AuthorizationException('Create a DJ profile before going live.');
        }

        $usernameSlug = $profile->handle ?: Str::slug($user->name).'-'.$user->id;
        $attributes = [
            'username_slug' => $usernameSlug,
            'title' => $profile->dj_name ?: $user->name,
            'description' => $profile->bio,
        ];

        $channel = $user->liveChannel()->first();

        if (! $channel) {
            return $user->liveChannel()->create([
                ...$attributes,
                'is_enabled' => true,
            ]);
        }

        $channel->forceFill($attributes)->save();

        return $channel->refresh();
    }

    private function agoraChannelName(LiveChannel $channel): string
    {
        return 'live-'.$channel->username_slug.'-'.Str::lower((string) Str::ulid());
    }
}
