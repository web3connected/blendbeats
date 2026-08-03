<?php

namespace Tests\Feature;

use App\Models\PayPalWebhookEvent;
use App\Models\User;
use App\Services\AffiliateReferralQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PayPalWebhookRecoveryTest extends TestCase
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
        ]);
    }

    public function test_recorded_unprocessed_event_is_recovered_without_creating_another_audit_row(): void
    {
        $user = $this->paypalUser('I-recover-success');
        $event = $this->event('BILLING.SUBSCRIPTION.ACTIVATED', 'I-recover-success');

        $this->artisan('payments:recover-paypal-webhook', ['event-id' => $event->id])
            ->expectsOutput("Recovering PayPal webhook event {$event->id}...")
            ->expectsOutput('Event type: BILLING.SUBSCRIPTION.ACTIVATED')
            ->expectsOutput('Resource ID: I-recover-success')
            ->expectsOutput('Recovery completed successfully.')
            ->assertSuccessful();

        $this->assertSame('active', $user->fresh()->paypal_subscription_status);
        $this->assertSame('dj_plus', $user->fresh()->media_storage_tier);
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertDatabaseCount('paypal_webhook_events', 1);
    }

    public function test_process_failure_rolls_back_and_can_be_retried_later(): void
    {
        $user = $this->paypalUser('I-recover-retry');
        $event = $this->event('BILLING.SUBSCRIPTION.ACTIVATED', 'I-recover-retry');
        $qualification = Mockery::mock(AffiliateReferralQualificationService::class);
        $qualification->shouldReceive('qualifySubscription')
            ->once()
            ->andThrow(new \RuntimeException('Synthetic recovery failure.'));
        $this->app->instance(AffiliateReferralQualificationService::class, $qualification);

        $this->artisan('payments:recover-paypal-webhook', ['event-id' => $event->id])
            ->expectsOutput('PayPal webhook recovery failed.')
            ->assertFailed();

        $this->assertSame('approved', $user->fresh()->paypal_subscription_status);
        $this->assertSame('free', $user->fresh()->media_storage_tier);
        $this->assertNull($event->fresh()->processed_at);

        $qualification = Mockery::mock(AffiliateReferralQualificationService::class);
        $qualification->shouldReceive('qualifySubscription')->once()->andReturnNull();
        $this->app->instance(AffiliateReferralQualificationService::class, $qualification);

        $this->artisan('payments:recover-paypal-webhook', ['event-id' => $event->id])
            ->assertSuccessful();

        $this->assertSame('active', $user->fresh()->paypal_subscription_status);
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertDatabaseCount('paypal_webhook_events', 1);
    }

    public function test_already_processed_event_is_refused_and_affiliate_processing_is_not_repeated(): void
    {
        $this->paypalUser('I-recover-once');
        $event = $this->event('BILLING.SUBSCRIPTION.ACTIVATED', 'I-recover-once');
        $qualification = Mockery::mock(AffiliateReferralQualificationService::class);
        $qualification->shouldReceive('qualifySubscription')->once()->andReturnNull();
        $this->app->instance(AffiliateReferralQualificationService::class, $qualification);

        $this->artisan('payments:recover-paypal-webhook', ['event-id' => $event->id])
            ->assertSuccessful();
        $this->artisan('payments:recover-paypal-webhook', ['event-id' => $event->id])
            ->expectsOutput('PayPal webhook recovery failed.')
            ->assertFailed();

        $this->assertDatabaseCount('paypal_webhook_events', 1);
    }

    public function test_missing_event_returns_failure(): void
    {
        $this->artisan('payments:recover-paypal-webhook', ['event-id' => 999999])
            ->expectsOutput('PayPal webhook event was not found.')
            ->assertFailed();
    }

    public function test_unknown_event_is_marked_processed_without_changing_the_user(): void
    {
        $user = $this->paypalUser('I-recover-unknown');
        $event = $this->event('CATALOG.PRODUCT.UPDATED', 'I-recover-unknown');

        $this->artisan('payments:recover-paypal-webhook', ['event-id' => $event->id])
            ->assertSuccessful();

        $this->assertSame('approved', $user->fresh()->paypal_subscription_status);
        $this->assertSame('free', $user->fresh()->media_storage_tier);
        $this->assertNotNull($event->fresh()->processed_at);
    }

    public function test_unmatched_user_event_is_marked_processed(): void
    {
        $event = $this->event('BILLING.SUBSCRIPTION.ACTIVATED', 'I-no-matching-user');

        $this->artisan('payments:recover-paypal-webhook', ['event-id' => $event->id])
            ->assertSuccessful();

        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_non_paypal_owned_user_is_not_changed_and_event_is_marked_processed(): void
    {
        $user = User::factory()->create(['media_storage_tier' => 'free']);
        $user->forceFill([
            'billing_provider' => 'stripe',
            'paypal_subscription_id' => 'I-owned-by-stripe',
            'paypal_subscription_status' => 'approved',
        ])->save();
        $event = $this->event('BILLING.SUBSCRIPTION.ACTIVATED', 'I-owned-by-stripe');

        $this->artisan('payments:recover-paypal-webhook', ['event-id' => $event->id])
            ->assertSuccessful();

        $this->assertSame('approved', $user->fresh()->paypal_subscription_status);
        $this->assertSame('free', $user->fresh()->media_storage_tier);
        $this->assertNotNull($event->fresh()->processed_at);
    }

    public function test_event_owned_by_another_recovery_worker_is_refused(): void
    {
        $event = $this->event('BILLING.SUBSCRIPTION.ACTIVATED', 'I-recovery-locked');
        $lock = Cache::lock('paypal-webhook-recovery:'.$event->id, 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('payments:recover-paypal-webhook', ['event-id' => $event->id])
                ->expectsOutput('PayPal webhook recovery failed.')
                ->assertFailed();
        } finally {
            $lock->release();
        }

        $this->assertNull($event->fresh()->processed_at);
    }

    private function paypalUser(string $subscriptionId): User
    {
        $user = User::factory()->create(['media_storage_tier' => 'free']);
        $user->forceFill([
            'billing_provider' => 'paypal',
            'paypal_subscription_id' => $subscriptionId,
            'paypal_plan_id' => 'sandbox-plus-plan-id',
            'paypal_subscription_status' => 'approved',
        ])->save();

        return $user;
    }

    private function event(string $eventType, string $resourceId): PayPalWebhookEvent
    {
        return PayPalWebhookEvent::query()->create([
            'event_type' => $eventType,
            'resource_id' => $resourceId,
            'payload' => [
                'id' => 'WH-'.strtoupper(substr(hash('sha256', $eventType.$resourceId), 0, 12)),
                'event_type' => $eventType,
                'resource' => [
                    'id' => $resourceId,
                    'plan_id' => 'sandbox-plus-plan-id',
                ],
            ],
        ]);
    }
}
