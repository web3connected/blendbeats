<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Admin;
use App\Models\PayPalWebhookEvent;
use App\Models\PaymentProvider;
use App\Http\Controllers\Api\PayPalWebhookController;
use App\Services\AffiliateReferralQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Laravel\Cashier\Billable;
use Tests\TestCase;

class BillingSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.paypal.mode' => 'sandbox',
            'billing.paypal.client_id' => 'sandbox-client-id',
            'billing.paypal.secret' => 'sandbox-secret',
            'billing.paypal.webhook_id' => 'sandbox-webhook-id',
            'billing.paypal.plans.dj_plus' => 'sandbox-plus-plan-id',
            'billing.paypal.plans.dj_pro' => 'sandbox-pro-plan-id',
            'billing.paypal.plans.dj_elite' => 'sandbox-elite-plan-id',
            'billing.paypal.enforce_signature' => false,
            'billing.paypal.enforce_duplicates' => false,
            'billing.paypal.enforce_replay_protection' => false,
            'billing.paypal.enforce_processing_lock' => false,
        ]);
    }

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
            'PAYPAL_PLAN_DJ_PRO' => 'live-pro-plan-id',
            'PAYPAL_PLAN_DJ_ELITE' => 'live-elite-plan-id',
            'TEST_PAYPAL_CLIENT_ID' => 'test-client-id',
            'TEST_PAYPAL_SECRET' => 'test-secret',
            'TEST_PAYPAL_PLAN_DJ_PLUS' => 'test-plan-id',
            'TEST_PAYPAL_PLAN_DJ_PRO' => 'test-pro-plan-id',
            'TEST_PAYPAL_PLAN_DJ_ELITE' => 'test-elite-plan-id',
            'TEST_PAYPAL_WEBHOOK_ID' => 'test-webhook-id',
        ]);

        $this->assertSame('sandbox', $billing['paypal']['mode']);
        $this->assertSame('test-client-id', $billing['paypal']['client_id']);
        $this->assertSame('test-secret', $billing['paypal']['secret']);
        $this->assertSame('test-plan-id', $billing['paypal']['plans']['dj_plus']);
        $this->assertSame('test-pro-plan-id', $billing['paypal']['plans']['dj_pro']);
        $this->assertSame('test-elite-plan-id', $billing['paypal']['plans']['dj_elite']);
        $this->assertSame('test-webhook-id', $billing['paypal']['webhook_id']);
    }

    public function test_paypal_sandbox_config_does_not_fall_back_to_live_values(): void
    {
        $billing = $this->billingConfigForEnv([
            'PAYPAL_MODE' => 'sandbox',
            'PAYPAL_CLIENT_ID' => 'live-client-id',
            'PAYPAL_SECRET' => 'live-secret',
            'PAYPAL_PLAN_DJ_PLUS' => 'live-plan-id',
            'PAYPAL_PLAN_DJ_PRO' => 'live-pro-plan-id',
            'PAYPAL_PLAN_DJ_ELITE' => 'live-elite-plan-id',
            'PAYPAL_WEBHOOK_ID' => 'live-webhook-id',
            'TEST_PAYPAL_CLIENT_ID' => null,
            'TEST_PAYPAL_SECRET' => null,
            'TEST_PAYPAL_PLAN_DJ_PLUS' => null,
            'TEST_PAYPAL_PLAN_DJ_PRO' => null,
            'TEST_PAYPAL_PLAN_DJ_ELITE' => null,
            'TEST_PAYPAL_WEBHOOK_ID' => null,
        ]);

        $this->assertNull($billing['paypal']['client_id']);
        $this->assertNull($billing['paypal']['secret']);
        $this->assertNull($billing['paypal']['plans']['dj_plus']);
        $this->assertNull($billing['paypal']['plans']['dj_pro']);
        $this->assertNull($billing['paypal']['plans']['dj_elite']);
        $this->assertNull($billing['paypal']['webhook_id']);
    }

    public function test_paypal_config_uses_live_credentials_in_live_mode(): void
    {
        $billing = $this->billingConfigForEnv([
            'PAYPAL_MODE' => 'live',
            'PAYPAL_CLIENT_ID' => 'live-client-id',
            'PAYPAL_SECRET' => 'live-secret',
            'PAYPAL_PLAN_DJ_PLUS' => 'live-plan-id',
            'PAYPAL_PLAN_DJ_PRO' => 'live-pro-plan-id',
            'PAYPAL_PLAN_DJ_ELITE' => 'live-elite-plan-id',
            'TEST_PAYPAL_CLIENT_ID' => 'test-client-id',
            'TEST_PAYPAL_SECRET' => 'test-secret',
            'TEST_PAYPAL_PLAN_DJ_PLUS' => 'test-plan-id',
            'TEST_PAYPAL_PLAN_DJ_PRO' => 'test-pro-plan-id',
            'TEST_PAYPAL_PLAN_DJ_ELITE' => 'test-elite-plan-id',
            'TEST_PAYPAL_WEBHOOK_ID' => 'test-webhook-id',
            'PAYPAL_WEBHOOK_ID' => 'live-webhook-id',
        ]);

        $this->assertSame('live', $billing['paypal']['mode']);
        $this->assertSame('live-client-id', $billing['paypal']['client_id']);
        $this->assertSame('live-secret', $billing['paypal']['secret']);
        $this->assertSame('live-plan-id', $billing['paypal']['plans']['dj_plus']);
        $this->assertSame('live-pro-plan-id', $billing['paypal']['plans']['dj_pro']);
        $this->assertSame('live-elite-plan-id', $billing['paypal']['plans']['dj_elite']);
        $this->assertSame('live-webhook-id', $billing['paypal']['webhook_id']);
    }

    public function test_invalid_paypal_mode_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PAYPAL_MODE must be either "sandbox" or "live".');

        $this->billingConfigForEnv([
            'PAYPAL_MODE' => 'production',
        ]);
    }

    public function test_paypal_security_enforcement_flags_remain_disabled_by_default(): void
    {
        $billing = $this->billingConfigForEnv([
            'PAYPAL_ENFORCE_SIGNATURE' => null,
            'PAYPAL_ENFORCE_DUPLICATES' => null,
            'PAYPAL_ENFORCE_REPLAY_PROTECTION' => null,
            'PAYPAL_ENFORCE_PROCESSING_LOCK' => null,
        ]);

        $this->assertFalse($billing['paypal']['enforce_signature']);
        $this->assertFalse($billing['paypal']['enforce_duplicates']);
        $this->assertFalse($billing['paypal']['enforce_replay_protection']);
        $this->assertFalse($billing['paypal']['enforce_processing_lock']);
    }

    public function test_paypal_readiness_fails_safely_for_missing_selected_environment_values(): void
    {
        config([
            'billing.paypal.mode' => 'sandbox',
            'billing.paypal.client_id' => 'sandbox-client-id',
            'billing.paypal.secret' => null,
            'billing.paypal.webhook_id' => null,
            'billing.paypal.plans.dj_plus' => null,
            'billing.paypal.plans.dj_pro' => null,
            'billing.paypal.plans.dj_elite' => null,
        ]);

        $readiness = PaymentProvider::paypalReadiness();

        $this->assertFalse($readiness['ready']);
        $this->assertSame('sandbox', $readiness['mode']);
        $this->assertSame([
            'secret',
            'dj_plus_plan_id',
            'dj_pro_plan_id',
            'dj_elite_plan_id',
            'webhook_id',
        ], $readiness['missing']);
        $this->assertArrayNotHasKey('client_id', $readiness);
        $this->assertArrayNotHasKey('secret', $readiness);
    }

    public function test_paypal_consumers_use_the_same_configuration_authority(): void
    {
        PaymentProvider::query()->updateOrCreate(
            ['provider' => 'paypal'],
            [
                'display_name' => 'PayPal',
                'mode' => 'live',
                'is_active' => true,
                'is_primary' => true,
                'client_id' => 'database-live-client-id',
                'secret' => 'database-live-secret',
                'webhook_id' => 'database-live-webhook-id',
            ],
        );

        $provider = PaymentProvider::where('provider', 'paypal')->firstOrFail();

        $this->assertSame('sandbox-client-id', $provider->effectiveValueFor('client_id'));
        $this->assertSame('configuration', $provider->valueSourceFor('client_id'));

        $controller = app(PayPalWebhookController::class);
        $initialize = new \ReflectionMethod($controller, 'initialize');
        $initialize->invoke($controller);

        foreach ([
            'paypalMode' => 'sandbox',
            'paypalClientId' => 'sandbox-client-id',
            'paypalSecret' => 'sandbox-secret',
            'webhookId' => 'sandbox-webhook-id',
        ] as $property => $expected) {
            $reflection = new \ReflectionProperty($controller, $property);
            $this->assertSame($expected, $reflection->getValue($controller));
        }
    }

    public function test_paypal_subscription_config_endpoint_returns_frontend_safe_config(): void
    {
        config([
            'billing.paypal.mode' => 'sandbox',
            'billing.paypal.client_id' => 'test-client-id',
            'billing.paypal.secret' => 'test-secret',
            'billing.paypal.webhook_id' => null,
            'billing.paypal.plans.dj_plus' => 'test-plan-id',
            'billing.paypal.plans.dj_pro' => 'test-pro-plan-id',
            'billing.paypal.plans.dj_elite' => 'test-elite-plan-id',
        ]);

        $this->getJson('/api/billing/paypal/subscription-config')
            ->assertOk()
            ->assertExactJson([
                'client_id' => 'test-client-id',
                'mode' => 'sandbox',
                'plan_id' => 'test-plan-id',
                'plans' => [
                    'dj_plus' => 'test-plan-id',
                    'dj_pro' => 'test-pro-plan-id',
                    'dj_elite' => 'test-elite-plan-id',
                ],
            ]);
    }

    public function test_browser_readiness_does_not_require_a_webhook_id(): void
    {
        config(['billing.paypal.webhook_id' => null]);

        $readiness = PaymentProvider::paypalReadiness();

        $this->assertTrue($readiness['browser_subscription']['ready']);
        $this->assertSame([], $readiness['browser_subscription']['missing']);
        $this->assertFalse($readiness['webhook_receipt']['ready']);
        $this->assertSame(['webhook_id'], $readiness['webhook_receipt']['missing']);
        $this->assertFalse($readiness['ready']);
        $this->assertContains('webhook_id', $readiness['missing']);
    }

    public function test_billing_payment_profile_syncs_paypal_mode_from_config(): void
    {
        config([
            'billing.paypal.mode' => 'live',
        ]);

        PaymentProvider::query()->updateOrCreate(
            ['provider' => 'paypal'],
            [
                'display_name' => 'PayPal',
                'mode' => 'sandbox',
                'is_active' => true,
                'is_primary' => true,
                'supported_features' => ['checkout', 'subscriptions'],
            ],
        );

        $this->getJson('/api/billing/plans')
            ->assertOk()
            ->assertJsonPath('payment_profile.primary_provider.provider', 'paypal')
            ->assertJsonPath('payment_profile.primary_provider.mode', 'live');

        $this->assertDatabaseHas('payment_providers', [
            'provider' => 'paypal',
            'mode' => 'live',
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
                'plan_id' => 'test-plan-id',
            ])
            ->assertOk()
            ->assertJson([
                'current_tier' => 'dj_plus',
                'paypal_subscription_id' => 'I-test-subscription',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'media_storage_tier' => 'dj_plus',
            'billing_provider' => 'paypal',
            'paypal_subscription_id' => 'I-test-subscription',
            'paypal_plan_id' => 'test-plan-id',
            'paypal_subscription_status' => 'approved',
        ]);

        $this->assertNotNull($user->fresh()->paypal_subscription_approved_at);
    }

    public function test_paypal_pro_subscription_approval_assigns_dj_pro(): void
    {
        $this->assertPayPalApprovalAssignsTier('dj_pro', 'sandbox-pro-plan-id');
    }

    public function test_paypal_elite_subscription_approval_assigns_dj_elite(): void
    {
        $this->assertPayPalApprovalAssignsTier('dj_elite', 'sandbox-elite-plan-id');
    }

    public function test_paypal_subscription_approval_rejects_unknown_plan_id(): void
    {
        $user = User::factory()->create(['media_storage_tier' => 'free']);

        $this->actingAs($user)
            ->postJson('/api/billing/paypal/subscription-approved', [
                'subscriptionID' => 'I-unknown-plan',
                'plan_id' => 'P-unknown-plan',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');

        $this->assertSame('free', $user->fresh()->media_storage_tier);
        $this->assertNull($user->fresh()->paypal_subscription_id);
    }

    public function test_account_subscription_details_endpoint_returns_logged_in_user_subscription_data(): void
    {
        $user = User::factory()->create([
            'media_storage_tier' => 'dj_plus',
        ]);
        $user->forceFill([
            'paypal_subscription_status' => 'active',
            'billing_provider' => 'internal',
            'paypal_subscription_id' => null,
            'paypal_subscription_approved_at' => now(),
            'comped_subscription_expires_at' => now()->addDays(7),
            'comped_subscription_reason' => 'Manual free DJ Plus test',
        ])->save();

        $this->actingAs($user)
            ->getJson('/api/account/subscription')
            ->assertOk()
            ->assertJson([
                'plan' => 'dj_plus',
                'status' => 'active',
                'billing_provider' => 'internal',
                'subscription_id' => null,
                'reason' => 'Manual free DJ Plus test',
            ])
            ->assertJsonStructure([
                'plan',
                'status',
                'billing_provider',
                'subscription_id',
                'approved_at',
                'expires_at',
                'reason',
            ]);
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
                'billing_provider' => 'paypal',
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
                'billing_provider' => 'paypal',
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
            'billing_provider' => 'paypal',
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

    public function test_paypal_webhook_process_rolls_back_business_updates_but_preserves_audit_record(): void
    {
        $user = User::factory()->create([
            'media_storage_tier' => 'free',
        ]);
        $user->forceFill([
            'billing_provider' => 'paypal',
            'paypal_subscription_id' => 'I-transaction-rollback',
            'paypal_subscription_status' => 'approved',
        ])->save();

        $qualification = \Mockery::mock(AffiliateReferralQualificationService::class);
        $qualification->shouldReceive('qualifySubscription')
            ->once()
            ->andThrow(new \RuntimeException('Synthetic qualification failure.'));
        $this->app->instance(AffiliateReferralQualificationService::class, $qualification);

        $this->postJson('/api/paypal/webhook', [
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => [
                'id' => 'I-transaction-rollback',
            ],
        ])->assertStatus(500)
            ->assertExactJson([
                'received' => false,
            ]);

        $user->refresh();
        $webhookEvent = PayPalWebhookEvent::where('resource_id', 'I-transaction-rollback')->firstOrFail();

        $this->assertSame('approved', $user->paypal_subscription_status);
        $this->assertSame('free', $user->media_storage_tier);
        $this->assertNull($webhookEvent->processed_at);
    }

    public function test_paypal_webhook_does_not_change_internal_subscriptions(): void
    {
        $user = User::factory()->create([
            'media_storage_tier' => 'dj_plus',
        ]);
        $user->forceFill([
            'billing_provider' => 'internal',
            'paypal_subscription_id' => 'I-internal-comped',
            'paypal_subscription_status' => 'active',
        ])->save();

        $this->postJson('/api/paypal/webhook', [
            'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED',
            'resource' => [
                'id' => 'I-internal-comped',
            ],
        ])->assertOk();

        $user->refresh();

        $this->assertSame('active', $user->paypal_subscription_status);
        $this->assertSame('dj_plus', $user->media_storage_tier);
        $this->assertSame('internal', $user->billing_provider);
    }

    public function test_admin_can_grant_free_dj_plus_subscription(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Subscription Admin',
            'email' => 'subscription-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'media_storage_tier' => 'free',
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson("/api/admin/users/{$user->id}/grant-free-subscription", [
                'reason' => 'Manual free DJ Plus test',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Free DJ Plus subscription granted.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'media_storage_tier' => 'dj_plus',
            'paypal_subscription_status' => 'active',
            'billing_provider' => 'internal',
            'paypal_subscription_id' => null,
            'paypal_plan_id' => null,
            'comped_subscription_reason' => 'Manual free DJ Plus test',
            'comped_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_revoke_free_dj_plus_subscription(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Subscription Admin',
            'email' => 'subscription-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'media_storage_tier' => 'dj_plus',
        ]);
        $user->forceFill([
            'paypal_subscription_status' => 'active',
            'billing_provider' => 'internal',
            'comped_subscription_reason' => 'Manual free DJ Plus test',
            'comped_by_user_id' => $admin->id,
        ])->save();

        $this->actingAs($admin, 'admin')
            ->postJson("/api/admin/users/{$user->id}/revoke-free-subscription")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Free DJ Plus subscription revoked.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'media_storage_tier' => 'free',
            'paypal_subscription_status' => 'cancelled',
            'billing_provider' => null,
            'comped_subscription_expires_at' => null,
            'comped_subscription_reason' => null,
            'comped_by_user_id' => null,
        ]);
    }

    public function test_admin_user_detail_page_shows_subscription_management_controls(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Subscription Admin',
            'email' => 'subscription-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'media_storage_tier' => 'dj_plus',
        ]);
        $user->forceFill([
            'paypal_subscription_status' => 'active',
            'billing_provider' => 'internal',
            'comped_subscription_reason' => 'Manual free DJ Plus test',
            'comped_by_user_id' => $admin->id,
        ])->save();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Subscription Management')
            ->assertSee('Current Plan')
            ->assertSee('Status')
            ->assertSee('Billing Provider')
            ->assertSee('Expires At')
            ->assertSee('Reason')
            ->assertSee('Grant Free DJ Plus')
            ->assertSee('Revoke Free Subscription')
            ->assertSee('Complimentary')
            ->assertSee('Manual free DJ Plus test');
    }

    private function assertPayPalApprovalAssignsTier(string $tier, string $planId): void
    {
        $user = User::factory()->create(['media_storage_tier' => 'free']);

        $this->actingAs($user)
            ->postJson('/api/billing/paypal/subscription-approved', [
                'subscriptionID' => 'I-'.$tier.'-subscription',
                'plan_id' => $planId,
            ])
            ->assertOk()
            ->assertJsonPath('current_tier', $tier);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'media_storage_tier' => $tier,
            'billing_provider' => 'paypal',
            'paypal_plan_id' => $planId,
            'paypal_subscription_status' => 'approved',
        ]);
    }

    private function billingConfigForEnv(array $values): array
    {
        $repository = Env::getRepository();
        $previous = [];

        foreach ($values as $key => $value) {
            $previous[$key] = Env::get($key);

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
