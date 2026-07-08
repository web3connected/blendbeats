<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\MembershipTierService;
use Tests\TestCase;

class MembershipTierServiceTest extends TestCase
{
    public function test_advertising_group_access_follows_membership_tier(): void
    {
        $service = new MembershipTierService;

        $freeUser = new User(['media_storage_tier' => 'free']);
        $proUser = new User(['media_storage_tier' => 'dj_pro']);
        $eliteUser = new User(['media_storage_tier' => 'dj_elite']);

        $this->assertTrue($service->canAccessAdvertisingGroup($freeUser, 'F'));
        $this->assertFalse($service->canAccessAdvertisingGroup($freeUser, 'E'));
        $this->assertTrue($service->canAccessAdvertisingGroup($proUser, 'C'));
        $this->assertFalse($service->canAccessAdvertisingGroup($proUser, 'B'));
        $this->assertTrue($service->canAccessAdvertisingGroup($eliteUser, 'A'));
    }

    public function test_legacy_storage_tier_aliases_resolve_to_membership_tiers(): void
    {
        $service = new MembershipTierService;

        $this->assertSame('free', $service->tierFor(new User(['media_storage_tier' => 'starter'])));
        $this->assertSame('dj_pro', $service->tierFor(new User(['media_storage_tier' => 'premium'])));
    }

    public function test_live_hosting_requires_paid_membership_tier(): void
    {
        $service = new MembershipTierService;

        $this->assertFalse($service->canGoLive(null));
        $this->assertFalse($service->canGoLive(new User(['media_storage_tier' => 'free'])));
        $this->assertFalse($service->canGoLive(new User(['media_storage_tier' => 'starter'])));
        $this->assertTrue($service->canGoLive(new User(['media_storage_tier' => 'dj_plus'])));
        $this->assertTrue($service->canGoLive(new User(['media_storage_tier' => 'dj_pro'])));
        $this->assertTrue($service->canGoLive(new User(['media_storage_tier' => 'dj_elite'])));
    }

    public function test_live_limits_follow_membership_tier(): void
    {
        $service = new MembershipTierService;

        $this->assertSame([
            'tier' => 'free',
            'can_go_live' => false,
            'max_stream_minutes' => null,
            'monthly_stream_limit' => 0,
            'can_record_live_streams' => false,
        ], $service->liveLimitsFor(new User(['media_storage_tier' => 'free'])));

        $this->assertSame([
            'tier' => 'dj_plus',
            'can_go_live' => true,
            'max_stream_minutes' => 30,
            'monthly_stream_limit' => 20,
            'can_record_live_streams' => false,
        ], $service->liveLimitsFor(new User(['media_storage_tier' => 'dj_plus'])));

        $this->assertSame([
            'tier' => 'dj_pro',
            'can_go_live' => true,
            'max_stream_minutes' => 60,
            'monthly_stream_limit' => 50,
            'can_record_live_streams' => true,
        ], $service->liveLimitsFor(new User(['media_storage_tier' => 'dj_pro'])));

        $this->assertSame([
            'tier' => 'dj_elite',
            'can_go_live' => true,
            'max_stream_minutes' => null,
            'monthly_stream_limit' => null,
            'can_record_live_streams' => true,
        ], $service->liveLimitsFor(new User(['media_storage_tier' => 'dj_elite'])));
    }

    public function test_scratch_video_monthly_limits_follow_membership_tier(): void
    {
        $service = new MembershipTierService;

        $this->assertSame(3, $service->scratchVideoMonthlyLimitFor(new User(['media_storage_tier' => 'free'])));
        $this->assertSame(50, $service->scratchVideoMonthlyLimitFor(new User(['media_storage_tier' => 'dj_plus'])));
        $this->assertSame(150, $service->scratchVideoMonthlyLimitFor(new User(['media_storage_tier' => 'dj_pro'])));
        $this->assertNull($service->scratchVideoMonthlyLimitFor(new User(['media_storage_tier' => 'dj_elite'])));
        $this->assertSame(50, $service->scratchVideoMonthlyLimitFor(new User(['media_storage_tier' => 'growth'])));
        $this->assertSame(150, $service->scratchVideoMonthlyLimitFor(new User(['media_storage_tier' => 'premium'])));
    }
}
