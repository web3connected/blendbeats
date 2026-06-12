<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Billable;
use Tests\TestCase;

class BillingSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_is_configured_for_cashier_billing(): void
    {
        $this->assertContains(Billable::class, class_uses_recursive(User::class));

        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'stripe_id' => null,
        ]);
    }

    public function test_subscription_tiers_are_configured(): void
    {
        $tiers = config('billing.subscription.tiers');

        $this->assertArrayHasKey('free', $tiers);
        $this->assertArrayHasKey('dj_plus', $tiers);
        $this->assertArrayHasKey('dj_pro', $tiers);
        $this->assertArrayHasKey('dj_elite', $tiers);
        $this->assertSame('test', config('billing.stripe.mode'));
        $this->assertSame(500 * 1024 * 1024, $tiers['free']['storage_bytes']);
        $this->assertSame(['F'], $tiers['free']['advertising_groups']);
        $this->assertSame(['E', 'F'], $tiers['dj_plus']['advertising_groups']);
        $this->assertSame(['C', 'D', 'E', 'F'], $tiers['dj_pro']['advertising_groups']);
        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F'], $tiers['dj_elite']['advertising_groups']);
        $this->assertContains('AI Booking Assistant', $tiers['dj_elite']['future_features']);
    }
}
