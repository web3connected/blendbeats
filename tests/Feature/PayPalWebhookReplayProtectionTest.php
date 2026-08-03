<?php

namespace Tests\Feature;

use App\Models\PayPalWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalWebhookReplayProtectionTest extends TestCase
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
            'billing.paypal.enforce_duplicates' => false,
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

    public function test_replay_enforcement_is_enabled_without_other_protections(): void
    {
        $this->assertTrue(config('billing.paypal.enforce_signature'));
        $this->assertTrue(config('billing.paypal.enforce_replay_protection'));
        $this->assertFalse(config('billing.paypal.enforce_duplicates'));
        $this->assertFalse(config('billing.paypal.enforce_processing_lock'));
    }

    public function test_fresh_signed_webhook_passes_replay_protection(): void
    {
        [$user, $payload, $headers] = $this->webhookFixture('fresh');

        $this->postSignedWebhook($payload, $headers)
            ->assertOk()
            ->assertExactJson(['received' => true]);

        $this->assertSame('active', $user->fresh()->paypal_subscription_status);
        $this->assertSame('dj_pro', $user->fresh()->media_storage_tier);
        $this->assertDatabaseCount('paypal_webhook_events', 1);
        $this->assertNotNull(PayPalWebhookEvent::firstOrFail()->processed_at);
        $this->assertSignatureWasVerified(1);
    }

    public function test_replay_inside_window_follows_disabled_duplicate_policy(): void
    {
        [, $payload, $headers] = $this->webhookFixture('inside-window');

        $this->postSignedWebhook($payload, $headers)->assertOk();
        $this->postSignedWebhook($payload, $headers)->assertOk();

        $this->assertDatabaseCount('paypal_webhook_events', 2);
        $this->assertSame(2, PayPalWebhookEvent::whereNotNull('processed_at')->count());
        $this->assertSignatureWasVerified(2);
    }

    public function test_exact_signed_replay_outside_window_is_rejected_before_record_or_process(): void
    {
        [$user, $payload, $headers] = $this->webhookFixture('expired');

        $this->postSignedWebhook($payload, $headers)->assertOk();
        $user->refresh();
        $state = $user->only(['billing_provider', 'media_storage_tier', 'paypal_subscription_status', 'paypal_plan_id']);
        $affiliateState = $this->affiliateCounts();

        $this->travel(301)->seconds();

        $this->postSignedWebhook($payload, $headers)
            ->assertStatus(500)
            ->assertExactJson(['received' => false]);

        $this->assertSame($state, $user->fresh()->only(array_keys($state)));
        $this->assertDatabaseCount('paypal_webhook_events', 1);
        $this->assertSame(1, PayPalWebhookEvent::whereNotNull('processed_at')->count());
        $this->assertSame($affiliateState, $this->affiliateCounts());
        $this->assertSignatureWasVerified(2);
    }

    private function webhookFixture(string $suffix): array
    {
        $subscriptionId = 'I-replay-'.$suffix;
        $user = User::factory()->create(['media_storage_tier' => 'free']);
        $user->forceFill([
            'billing_provider' => 'paypal',
            'paypal_subscription_id' => $subscriptionId,
            'paypal_plan_id' => 'sandbox-pro-plan-id',
            'paypal_subscription_status' => 'approved',
        ])->save();

        $payload = [
            'id' => 'WH-replay-'.$suffix,
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => [
                'id' => $subscriptionId,
                'plan_id' => 'sandbox-pro-plan-id',
            ],
        ];

        $headers = [
            'PAYPAL-TRANSMISSION-ID' => 'transmission-'.$suffix,
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
            'PAYPAL-TRANSMISSION-SIG' => 'test-signature',
            'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/cert.pem',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
        ];

        return [$user, $payload, $headers];
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
