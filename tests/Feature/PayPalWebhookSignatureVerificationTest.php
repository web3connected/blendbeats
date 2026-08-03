<?php

namespace Tests\Feature;

use App\Models\PayPalWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalWebhookSignatureVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'PAYPAL-TRANSMISSION-ID' => 'transmission-id',
        'PAYPAL-TRANSMISSION-TIME' => '2026-08-03T08:00:00Z',
        'PAYPAL-TRANSMISSION-SIG' => 'test-signature',
        'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/cert.pem',
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
    ];

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
            'billing.paypal.enforce_signature' => true,
            'billing.paypal.enforce_duplicates' => false,
            'billing.paypal.enforce_replay_protection' => false,
            'billing.paypal.enforce_processing_lock' => false,
        ]);
    }

    public function test_only_signature_enforcement_is_enabled(): void
    {
        $this->assertTrue(config('billing.paypal.enforce_signature'));
        $this->assertFalse(config('billing.paypal.enforce_duplicates'));
        $this->assertFalse(config('billing.paypal.enforce_replay_protection'));
        $this->assertFalse(config('billing.paypal.enforce_processing_lock'));
    }

    public function test_each_required_signature_header_fails_closed_when_missing(): void
    {
        Http::fake();

        foreach (array_keys(self::HEADERS) as $missingHeader) {
            $user = $this->paypalUser('I-missing-'.md5($missingHeader));
            $headers = self::HEADERS;
            unset($headers[$missingHeader]);

            $this->postRawWebhook($this->payload($user->paypal_subscription_id), $headers)
                ->assertStatus(500)
                ->assertExactJson(['received' => false]);

            $user->refresh();
            $this->assertSame('approved', $user->paypal_subscription_status, $missingHeader);
            $this->assertSame('free', $user->media_storage_tier, $missingHeader);
        }

        $this->assertSame(0, PayPalWebhookEvent::count());
        Http::assertNothingSent();
    }

    public function test_invalid_paypal_verification_fails_without_database_side_effects(): void
    {
        $user = $this->paypalUser('I-invalid-signature');
        $affiliateCounts = $this->affiliateCounts();
        $this->fakePayPalVerification('FAILURE');

        $this->postRawWebhook($this->payload($user->paypal_subscription_id), self::HEADERS)
            ->assertStatus(500)
            ->assertExactJson(['received' => false]);

        $user->refresh();
        $this->assertSame('approved', $user->paypal_subscription_status);
        $this->assertSame('free', $user->media_storage_tier);
        $this->assertSame(0, PayPalWebhookEvent::count());
        $this->assertSame($affiliateCounts, $this->affiliateCounts());
    }

    public function test_valid_mocked_verification_preserves_raw_event_and_processes_normally(): void
    {
        $user = $this->paypalUser('I-valid-signature');
        $payload = $this->payload($user->paypal_subscription_id);
        $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->fakePayPalVerification('SUCCESS');

        $this->call('POST', '/api/paypal/webhook', [], [], [], $this->serverHeaders(self::HEADERS), $rawPayload)
            ->assertOk()
            ->assertExactJson(['received' => true]);

        $user->refresh();
        $event = PayPalWebhookEvent::query()->where('resource_id', $user->paypal_subscription_id)->firstOrFail();

        $this->assertSame('active', $user->paypal_subscription_status);
        $this->assertSame('dj_plus', $user->media_storage_tier);
        $this->assertNotNull($event->processed_at);
        $this->assertSame($payload, $event->payload);

        Http::assertSent(function (Request $request) use ($payload): bool {
            return $request->url() === 'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature'
                && $request['webhook_id'] === 'sandbox-webhook-id'
                && $request['webhook_event'] === $payload;
        });
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

    private function payload(string $subscriptionId): array
    {
        return [
            'id' => 'WH-'.md5($subscriptionId),
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => [
                'id' => $subscriptionId,
                'plan_id' => 'sandbox-plus-plan-id',
            ],
        ];
    }

    private function postRawWebhook(array $payload, array $headers)
    {
        $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/paypal/webhook', [], [], [], $this->serverHeaders($headers), $rawPayload);
    }

    private function serverHeaders(array $headers): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.str_replace('-', '_', $name)] = $value;
        }

        return $server;
    }

    private function fakePayPalVerification(string $status): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'test-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => $status,
            ]),
        ]);
    }

    private function affiliateCounts(): array
    {
        return collect(['affiliate_referrals', 'affiliate_referral_events', 'affiliate_rewards'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }
}
