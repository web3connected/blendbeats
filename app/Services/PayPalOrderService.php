<?php

namespace App\Services;

use App\Models\PaymentProvider;
use Illuminate\Support\Facades\Http;

class PayPalOrderService
{
    /** @param array<string, mixed> $payload */
    public function create(PaymentProvider $provider, array $payload): array
    {
        return Http::withToken($this->accessToken($provider))
            ->acceptJson()
            ->post($this->baseUrl($provider).'/v2/checkout/orders', $payload)
            ->throw()
            ->json();
    }

    public function capture(PaymentProvider $provider, string $orderId): array
    {
        return Http::withToken($this->accessToken($provider))
            ->acceptJson()
            ->withBody('', 'application/json')
            ->post($this->baseUrl($provider)."/v2/checkout/orders/{$orderId}/capture")
            ->throw()
            ->json();
    }

    private function accessToken(PaymentProvider $provider): string
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) $provider->effectiveValueFor('client_id'),
                (string) $provider->effectiveValueFor('secret'),
            )
            ->post($this->baseUrl($provider).'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()
            ->json();

        return (string) $response['access_token'];
    }

    private function baseUrl(PaymentProvider $provider): string
    {
        return $provider->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
