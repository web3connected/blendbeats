<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PayPalWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
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

    public function test_paypal_config_uses_test_credentials_in_sandbox_mode(): void
    {
        $billing = $this->billingConfigForEnv([
            'PAYPAL_MODE' => 'sandbox',
            'PAYPAL_CLIENT_ID' => 'live-client-id',
            'PAYPAL_SECRET' => 'live-secret',
            'PAYPAL_PLAN_DJ_PLUS' => 'live-plan-id',
            'TEST_PAYPAL_CLIENT_ID' => 'test-client-id',
            'TEST_PAYPAL_SECRET' => 'test-secret',
            'TEST_PAYPAL_PLAN_DJ_PLUS' => 'test-plan-id',
        ]);

        $this->assertSame('sandbox', $billing['paypal']['mode']);
        $this->assertSame('test-client-id', $billing['paypal']['client_id']);
        $this->assertSame('test-secret', $billing['paypal']['secret']);
        $this->assertSame('test-plan-id', $billing['paypal']['plans']['dj_plus']);
    }

    public function test_paypal_config_uses_live_credentials_in_live_mode(): void
    {
        $billing = $this->billingConfigForEnv([
            'PAYPAL_MODE' => 'live',
            'PAYPAL_CLIENT_ID' => 'live-client-id',
            'PAYPAL_SECRET' => 'live-secret',
            'PAYPAL_PLAN_DJ_PLUS' => 'live-plan-id',
            'TEST_PAYPAL_CLIENT_ID' => 'test-client-id',
            'TEST_PAYPAL_SECRET' => 'test-secret',
            'TEST_PAYPAL_PLAN_DJ_PLUS' => 'test-plan-id',
        ]);

        $this->assertSame('live', $billing['paypal']['mode']);
        $this->assertSame('live-client-id', $billing['paypal']['client_id']);
        $this->assertSame('live-secret', $billing['paypal']['secret']);
        $this->assertSame('live-plan-id', $billing['paypal']['plans']['dj_plus']);
    }

    public function test_paypal_subscription_config_endpoint_returns_frontend_safe_config(): void
    {
        config([
            'billing.paypal.mode' => 'sandbox',
            'billing.paypal.client_id' => 'test-client-id',
            'billing.paypal.secret' => 'test-secret',
            'billing.paypal.plans.dj_plus' => 'test-plan-id',
        ]);

        $this->getJson('/api/billing/paypal/subscription-config')
            ->assertOk()
            ->assertExactJson([
                'client_id' => 'test-client-id',
                'mode' => 'sandbox',
                'plan_id' => 'test-plan-id',
            ]);
    }

    public function test_paypal_subscription_approval_endpoint_saves_subscription_for_logged_in_user(): void
    {
        config([
            'billing.paypal.plans.dj_plus' => 'test-plan-id',
        ]);

        $user = User::factory()->create([
            'media_storage_tier' => 'free',
        ]);

        $this->actingAs($user)
            ->postJson('/api/billing/paypal/subscription-approved', [
                'subscriptionID' => 'I-test-subscription',
            ])
            ->assertOk()
            ->assertJson([
                'current_tier' => 'dj_plus',
                'paypal_subscription_id' => 'I-test-subscription',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'media_storage_tier' => 'dj_plus',
            'paypal_subscription_id' => 'I-test-subscription',
            'paypal_plan_id' => 'test-plan-id',
            'paypal_subscription_status' => 'approved',
        ]);

        $this->assertNotNull($user->fresh()->paypal_subscription_approved_at);
    }

    public function test_paypal_webhook_endpoint_stores_raw_event_payload(): void
    {
        $this->postJson('/api/paypal/webhook', [
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => [
                'id' => 'TEST-SUBSCRIPTION-123',
            ],
        ])->assertOk()
            ->assertJson([
                'received' => true,
            ]);

        $this->assertDatabaseHas('paypal_webhook_events', [
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource_id' => 'TEST-SUBSCRIPTION-123',
        ]);

        $webhookEvent = PayPalWebhookEvent::where('resource_id', 'TEST-SUBSCRIPTION-123')->first();

        $this->assertNotNull($webhookEvent);
        $this->assertNotNull($webhookEvent->processed_at);
    }

    public function test_paypal_webhook_active_events_set_user_to_dj_plus(): void
    {
        foreach (['BILLING.SUBSCRIPTION.ACTIVATED', 'BILLING.SUBSCRIPTION.RE-ACTIVATED'] as $eventType) {
            $user = User::factory()->create([
                'media_storage_tier' => 'free',
            ]);
            $user->forceFill([
                'paypal_subscription_id' => "I-{$eventType}",
                'paypal_subscription_status' => 'approved',
            ])->save();

            $this->postJson('/api/paypal/webhook', [
                'event_type' => $eventType,
                'resource' => [
                    'id' => "I-{$eventType}",
                ],
            ])->assertOk();

            $user->refresh();

            $this->assertSame('active', $user->paypal_subscription_status);
            $this->assertSame('dj_plus', $user->media_storage_tier);
        }
    }

    public function test_paypal_webhook_terminal_events_set_user_to_free(): void
    {
        $events = [
            'BILLING.SUBSCRIPTION.CANCELLED' => 'cancelled',
            'BILLING.SUBSCRIPTION.SUSPENDED' => 'suspended',
            'BILLING.SUBSCRIPTION.EXPIRED' => 'expired',
        ];

        foreach ($events as $eventType => $expectedStatus) {
            $user = User::factory()->create([
                'media_storage_tier' => 'dj_plus',
            ]);
            $user->forceFill([
                'paypal_subscription_id' => "I-{$expectedStatus}",
                'paypal_subscription_status' => 'active',
            ])->save();

            $this->postJson('/api/paypal/webhook', [
                'event_type' => $eventType,
                'resource' => [
                    'id' => "I-{$expectedStatus}",
                ],
            ])->assertOk();

            $user->refresh();

            $this->assertSame($expectedStatus, $user->paypal_subscription_status);
            $this->assertSame('free', $user->media_storage_tier);
        }
    }

    public function test_paypal_webhook_payment_failed_keeps_user_on_dj_plus_for_now(): void
    {
        $user = User::factory()->create([
            'media_storage_tier' => 'dj_plus',
        ]);
        $user->forceFill([
            'paypal_subscription_id' => 'I-payment-failed',
            'paypal_subscription_status' => 'active',
        ])->save();

        $this->postJson('/api/paypal/webhook', [
            'event_type' => 'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
            'resource' => [
                'id' => 'I-payment-failed',
            ],
        ])->assertOk();

        $user->refresh();

        $this->assertSame('payment_failed', $user->paypal_subscription_status);
        $this->assertSame('dj_plus', $user->media_storage_tier);
    }

    private function billingConfigForEnv(array $values): array
    {
        $repository = Env::getRepository();
        $previous = [];

        foreach ($values as $key => $value) {
            $previous[$key] = Env::get($key);
            $repository->set($key, $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            return require base_path('config/billing.php');
        } finally {
            foreach ($previous as $key => $value) {
                if ($value === null) {
                    $repository->clear($key);
                    unset($_ENV[$key], $_SERVER[$key]);
                    putenv($key);

                    continue;
                }

                $repository->set($key, $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}
