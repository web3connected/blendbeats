<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\MembershipTierService;
use Tests\TestCase;

class MembershipTierServiceTest extends TestCase
{
    public function test_advertising_group_access_follows_membership_tier(): void
    {
        $service = new MembershipTierService();

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
        $service = new MembershipTierService();

        $this->assertSame('free', $service->tierFor(new User(['media_storage_tier' => 'starter'])));
        $this->assertSame('dj_pro', $service->tierFor(new User(['media_storage_tier' => 'premium'])));
    }
}
