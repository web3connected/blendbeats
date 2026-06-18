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
        return $this->configFor($user)['advertising_groups'] ?? [];
    }

    public function canAccessAdvertisingGroup(?User $user, string $group): bool
    {
        return in_array(strtoupper($group), $this->advertisingGroupsFor($user), true);
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
