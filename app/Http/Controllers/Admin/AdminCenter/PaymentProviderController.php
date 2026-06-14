<?php

namespace App\Http\Controllers\Admin\AdminCenter;

use App\Http\Controllers\Controller;
use App\Models\PaymentProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentProviderController extends Controller
{
    public function index(): View
    {
        $this->ensureDefaultProviders();

        $providers = PaymentProvider::query()
            ->orderByDesc('is_primary')
            ->orderBy('display_name')
            ->get();

        return view('admin.payment-providers.index', [
            'providers' => $providers,
            'activeProviders' => $providers->where('is_active', true)->values(),
            'configuredCount' => $providers->filter(fn (PaymentProvider $provider): bool => filled($provider->client_id) && $provider->hasSecret())->count(),
            'activeCount' => $providers->where('is_active', true)->count(),
            'primaryProvider' => $providers->firstWhere('is_primary', true),
        ]);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active_providers' => ['array'],
            'active_providers.*' => ['integer', Rule::exists('payment_providers', 'id')],
            'primary_provider' => ['nullable', 'integer', Rule::exists('payment_providers', 'id')],
        ]);

        $activeProviderIds = collect($validated['active_providers'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $primaryProviderId = isset($validated['primary_provider']) ? (int) $validated['primary_provider'] : null;

        if ($primaryProviderId !== null && ! $activeProviderIds->contains($primaryProviderId)) {
            $activeProviderIds->push($primaryProviderId);
        }

        PaymentProvider::query()->get()->each(function (PaymentProvider $provider) use ($activeProviderIds, $primaryProviderId): void {
            $provider->forceFill([
                'is_active' => $activeProviderIds->contains($provider->id),
                'is_primary' => $primaryProviderId === $provider->id,
            ])->save();
        });

        if ($primaryProviderId === null && $activeProviderIds->isNotEmpty()) {
            PaymentProvider::query()
                ->whereKey($activeProviderIds->first())
                ->update(['is_primary' => true]);
        }

        return redirect()
            ->route('admin.admincenter.paymentproviders.index')
            ->with('status', 'Payment provider switches updated.');
    }

    public function update(Request $request, PaymentProvider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'mode' => ['required', 'string', Rule::in(['sandbox', 'test', 'live', 'production'])],
            'client_id' => ['nullable', 'string', 'max:500'],
            'secret' => ['nullable', 'string', 'max:2000'],
            'clear_secret' => ['nullable', 'boolean'],
            'webhook_id' => ['nullable', 'string', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'max:2000'],
            'clear_webhook_secret' => ['nullable', 'boolean'],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'dashboard_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'supported_features' => ['nullable', 'string', 'max:1000'],
        ]);

        $provider->fill([
            'display_name' => $validated['display_name'],
            'mode' => $validated['mode'],
            'client_id' => $validated['client_id'] ?? null,
            'webhook_id' => $validated['webhook_id'] ?? null,
            'merchant_id' => $validated['merchant_id'] ?? null,
            'dashboard_url' => $validated['dashboard_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'supported_features' => $this->featuresFromInput($validated['supported_features'] ?? ''),
        ]);

        if ($request->boolean('clear_secret')) {
            $provider->secret = null;
        } elseif (filled($validated['secret'] ?? null)) {
            $provider->secret = $validated['secret'];
        }

        if ($request->boolean('clear_webhook_secret')) {
            $provider->webhook_secret = null;
        } elseif (filled($validated['webhook_secret'] ?? null)) {
            $provider->webhook_secret = $validated['webhook_secret'];
        }

        $provider->save();

        return redirect()
            ->route('admin.admincenter.paymentproviders.index')
            ->with('status', "{$provider->display_name} payment provider updated.");
    }

    private function ensureDefaultProviders(): void
    {
        $defaults = [
            'paypal' => [
                'display_name' => 'PayPal',
                'mode' => config('billing.paypal.mode', 'sandbox'),
                'is_active' => true,
                'is_primary' => true,
                'client_id' => config('billing.paypal.client_id'),
                'webhook_id' => config('billing.paypal.webhook_id'),
                'merchant_id' => config('billing.paypal.merchant_id'),
                'dashboard_url' => 'https://www.paypal.com/businessmanage/account',
                'supported_features' => ['checkout', 'subscriptions', 'promotion_payments'],
            ],
            'stripe' => [
                'display_name' => 'Stripe',
                'mode' => config('billing.stripe.mode', 'test'),
                'is_active' => false,
                'is_primary' => false,
                'client_id' => config('cashier.key'),
                'webhook_id' => null,
                'merchant_id' => null,
                'dashboard_url' => 'https://dashboard.stripe.com/test/dashboard',
                'supported_features' => ['checkout', 'subscriptions', 'billing_portal', 'webhooks'],
            ],
        ];

        collect($defaults)->each(function (array $data, string $provider): void {
            $paymentProvider = PaymentProvider::query()->firstOrCreate(['provider' => $provider], $data);

            $updates = collect(['client_id', 'webhook_id', 'merchant_id'])
                ->filter(fn (string $field): bool => blank($paymentProvider->{$field}) && filled($data[$field] ?? null))
                ->mapWithKeys(fn (string $field): array => [$field => $data[$field]]);

            if ($updates->isNotEmpty()) {
                $paymentProvider->forceFill($updates->all())->save();
            }
        });
    }

    private function featuresFromInput(string $features): array
    {
        return str($features)
            ->explode(',')
            ->map(fn (string $feature): string => trim($feature))
            ->filter()
            ->values()
            ->all();
    }
}
