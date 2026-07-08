<?php

namespace App\Services;

use App\Models\User;

class MembershipTierService
{
    private const LIVE_LIMITS = [
        'free' => [
            'can_go_live' => false,
            'max_stream_minutes' => null,
            'monthly_stream_limit' => 0,
            'can_record_live_streams' => false,
        ],
        'dj_plus' => [
            'can_go_live' => true,
            'max_stream_minutes' => 30,
            'monthly_stream_limit' => 20,
            'can_record_live_streams' => false,
        ],
        'dj_pro' => [
            'can_go_live' => true,
            'max_stream_minutes' => 60,
            'monthly_stream_limit' => 50,
            'can_record_live_streams' => true,
        ],
        'dj_elite' => [
            'can_go_live' => true,
            'max_stream_minutes' => null,
            'monthly_stream_limit' => null,
            'can_record_live_streams' => true,
        ],
    ];

    public function tierFor(?User $user): string
    {
        $tier = $user?->media_storage_tier ?: config('billing.subscription.free_tier', 'free');

        return config("media_storage.tier_aliases.{$tier}", $tier);
    }

    public function configFor(?User $user): array
    {
        $tier = $this->tierFor($user);

        return config("billing.subscription.tiers.{$tier}")
            ?? config('billing.subscription.tiers.'.config('billing.subscription.free_tier', 'free'));
    }

    public function advertisingGroupsFor(?User $user): array
    {
        return $this->configFor($user)['advertising_groups'] ?? [];
    }

    public function canAccessAdvertisingGroup(?User $user, string $group): bool
    {
        return in_array(strtoupper($group), $this->advertisingGroupsFor($user), true);
    }

    public function canGoLive(?User $user): bool
    {
        return $this->liveLimitsFor($user)['can_go_live'];
    }

    public function liveLimitsFor(?User $user): array
    {
        $tier = $this->tierFor($user);
        $limits = self::LIVE_LIMITS[$tier] ?? self::LIVE_LIMITS[config('billing.subscription.free_tier', 'free')];

        return [
            'tier' => $tier,
            'can_go_live' => (bool) $limits['can_go_live'],
            'max_stream_minutes' => $limits['max_stream_minutes'],
            'monthly_stream_limit' => $limits['monthly_stream_limit'],
            'can_record_live_streams' => (bool) $limits['can_record_live_streams'],
        ];
    }

    public function liveMaxStreamMinutesFor(?User $user): ?int
    {
        $limit = $this->liveLimitsFor($user)['max_stream_minutes'];

        return $limit === null ? null : (int) $limit;
    }

    public function liveMonthlyStreamLimitFor(?User $user): ?int
    {
        $limit = $this->liveLimitsFor($user)['monthly_stream_limit'];

        return $limit === null ? null : (int) $limit;
    }

    public function canRecordLiveStreams(?User $user): bool
    {
        return $this->liveLimitsFor($user)['can_record_live_streams'];
    }

    public function storageBytesFor(?User $user): int
    {
        return (int) ($this->configFor($user)['storage_bytes'] ?? 0);
    }

    public function scratchVideoMonthlyLimitFor(?User $user): ?int
    {
        $tier = $this->tierFor($user);
        $limit = config("media_storage.scratch_video_monthly_limits.{$tier}");

        return $limit === null ? null : (int) $limit;
    }
}
