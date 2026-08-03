<?php

namespace Tests\Feature;

use App\Models\PayPalWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalWebhookDuplicateProtectionTest extends TestCase
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
            'billing.paypal.replay_window_seconds' => 300,
            'billing.paypal.enforce_signature' => true,
            'billing.paypal.enforce_replay_protection' => true,
            'billing.paypal.enforce_duplicates' => true,
            'billing.paypal.enforce_processing_lock' => false,
        ]);

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'test-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ]),
        ]);
    }

    public function test_duplicate_enforcement_is_enabled_with_processing_lock_disabled(): void
    {
        $this->assertTrue(config('billing.paypal.enforce_signature'));
        $this->assertTrue(config('billing.paypal.enforce_replay_protection'));
        $this->assertTrue(config('billing.paypal.enforce_duplicates'));
        $this->assertFalse(config('billing.paypal.enforce_processing_lock'));
    }

    public function test_fresh_signed_webhook_succeeds_with_duplicate_enforcement(): void
    {
        [$user, $payload, $headers] = $this->webhookFixture('fresh');

        $this->postSignedWebhook($payload, $headers)
            ->assertOk()
            ->assertExactJson(['received' => true]);

        $this->assertSame('active', $user->fresh()->paypal_subscription_status);
        $this->assertSame('dj_plus', $user->fresh()->media_storage_tier);
        $this->assertDatabaseCount('paypal_webhook_events', 1);
        $this->assertNotNull(PayPalWebhookEvent::firstOrFail()->processed_at);
        $this->assertSignatureWasVerified(1);
    }

    public function test_exact_signed_duplicate_is_rejected_before_record_or_business_processing(): void
    {
        [$user, $payload, $headers] = $this->webhookFixture('duplicate');

        $this->postSignedWebhook($payload, $headers)->assertOk();
        $user->refresh();
        $state = $user->only(['billing_provider', 'media_storage_tier', 'paypal_subscription_status', 'paypal_plan_id']);
        $affiliateState = $this->affiliateCounts();

        $this->postSignedWebhook($payload, $headers)
            ->assertStatus(500)
            ->assertExactJson(['received' => false]);

        $this->assertLessThanOrEqual(
            config('billing.paypal.replay_window_seconds'),
            now()->diffInSeconds($headers['PAYPAL-TRANSMISSION-TIME']),
        );
        $this->assertSame($state, $user->fresh()->only(array_keys($state)));
        $this->assertDatabaseCount('paypal_webhook_events', 1);
        $this->assertSame(1, PayPalWebhookEvent::whereNotNull('processed_at')->count());
        $this->assertSame($affiliateState, $this->affiliateCounts());
        $this->assertSignatureWasVerified(2);
    }

    private function webhookFixture(string $suffix): array
    {
        $subscriptionId = 'I-duplicate-'.$suffix;
        $user = User::factory()->create(['media_storage_tier' => 'free']);
        $user->forceFill([
            'billing_provider' => 'paypal',
            'paypal_subscription_id' => $subscriptionId,
            'paypal_plan_id' => 'sandbox-plus-plan-id',
            'paypal_subscription_status' => 'approved',
        ])->save();

        return [
            $user,
            [
                'id' => 'WH-duplicate-'.$suffix,
                'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                'resource' => [
                    'id' => $subscriptionId,
                    'plan_id' => 'sandbox-plus-plan-id',
                ],
            ],
            [
                'PAYPAL-TRANSMISSION-ID' => 'transmission-'.$suffix,
                'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
                'PAYPAL-TRANSMISSION-SIG' => 'test-signature',
                'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/cert.pem',
                'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            ],
        ];
    }

    private function postSignedWebhook(array $payload, array $headers)
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.str_replace('-', '_', $name)] = $value;
        }

        return $this->call(
            'POST',
            '/api/paypal/webhook',
            [],
            [],
            [],
            $server,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    private function assertSignatureWasVerified(int $times): void
    {
        $sent = Http::recorded(fn (Request $request): bool =>
            str_ends_with($request->url(), '/v1/notifications/verify-webhook-signature')
        );

        $this->assertCount($times, $sent);
    }

    private function affiliateCounts(): array
    {
        return collect(['affiliate_referrals', 'affiliate_referral_events', 'affiliate_rewards'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }
}
