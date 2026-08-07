<?php

namespace App\Services;

use App\Models\User;

class MembershipTierService
{
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
        return $this->serviceConfigFor($user, 'advertising')['groups']
            ?? $this->configFor($user)['advertising_groups']
            ?? [];
    }

    public function canAccessAdvertisingGroup(?User $user, string $group): bool
    {
        return in_array(strtoupper($group), $this->advertisingGroupsFor($user), true);
    }

    public function advertisingLevelFor(?User $user): string
    {
        return (string) ($this->serviceConfigFor($user, 'advertising')['level'] ?? 'none');
    }

    public function serviceConfigFor(?User $user, string $service): array
    {
        return $this->configFor($user)['services'][$service] ?? [];
    }

    public function serviceEnabledFor(?User $user, string $service): bool
    {
        return (bool) ($this->serviceConfigFor($user, $service)['enabled'] ?? false);
    }

    public function canGoLive(?User $user): bool
    {
        return $this->serviceEnabledFor($user, 'live_streaming');
    }

    public function liveLimitsFor(?User $user): array
    {
        $tier = $this->tierFor($user);
        $limits = $this->serviceConfigFor($user, 'live_streaming');

        return [
            'tier' => $tier,
            'can_go_live' => (bool) ($limits['enabled'] ?? false),
            'max_stream_minutes' => $limits['max_duration_minutes'] ?? null,
            'weekly_stream_limit' => $limits['weekly_limit'] ?? null,
            'monthly_stream_limit' => $limits['monthly_limit'] ?? null,
            'can_record_live_streams' => (bool) ($limits['recording_enabled'] ?? false),
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

    public function liveWeeklyStreamLimitFor(?User $user): ?int
    {
        $limit = $this->liveLimitsFor($user)['weekly_stream_limit'];

        return $limit === null ? null : (int) $limit;
    }

    public function canRecordLiveStreams(?User $user): bool
    {
        return $this->liveLimitsFor($user)['can_record_live_streams'];
    }

    public function storageBytesFor(?User $user): int
    {
        return (int) (
            $this->serviceConfigFor($user, 'storage')['max_storage_bytes']
            ?? $this->configFor($user)['storage_bytes']
            ?? 0
        );
    }

    public function maxBattleWagerCoinsFor(?User $user): ?int
    {
        $limit = $this->serviceConfigFor($user, 'battles')['max_wager_coins'] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    public function canUseBookings(?User $user): bool
    {
        return $this->serviceEnabledFor($user, 'bookings');
    }

    public function scratchVideoMonthlyLimitFor(?User $user): ?int
    {
        $tier = $this->tierFor($user);
        $limit = config("media_storage.scratch_video_monthly_limits.{$tier}");

        return $limit === null ? null : (int) $limit;
    }
}
