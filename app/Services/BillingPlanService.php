<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Number;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

class BillingPlanService
{
    public function plans(?User $user = null): array
    {
        return collect(config('billing.subscription.tiers', []))
            ->map(fn (array $tier, string $key): array => $this->planPayload($key, $tier, $user))
            ->values()
            ->all();
    }

    public function plan(string $key, ?User $user = null): ?array
    {
        $tier = config("billing.subscription.tiers.{$key}");

        if (! is_array($tier)) {
            return null;
        }

        return $this->planPayload($key, $tier, $user);
    }

    public function stripePriceIdFor(string $key): ?string
    {
        $priceId = config("billing.subscription.tiers.{$key}.stripe_price_id");

        return is_string($priceId) && $priceId !== '' ? $priceId : null;
    }

    /**
     * @throws ApiErrorException
     */
    public function checkoutPriceIdFor(string $key): ?string
    {
        $configuredPriceId = $this->stripePriceIdFor($key);

        if ($configuredPriceId) {
            return $configuredPriceId;
        }

        $tier = config("billing.subscription.tiers.{$key}");

        if (! is_array($tier) || (int) ($tier['price_cents'] ?? 0) <= 0) {
            return null;
        }

        $lookupKey = $this->stripeLookupKeyFor($key);

        if (! $lookupKey) {
            return null;
        }

        $stripe = Cashier::stripe();
        $prices = $stripe->prices->all([
            'active' => true,
            'limit' => 1,
            'lookup_keys' => [$lookupKey],
        ]);

        if (! empty($prices->data)) {
            return $prices->data[0]->id;
        }

        $product = $stripe->products->create([
            'name' => $tier['name'] ?? str($key)->headline()->toString(),
            'metadata' => [
                'blendbeats_plan' => $key,
                'blendbeats_type' => config('billing.subscription.default_type', 'dj_membership'),
            ],
        ]);

        $price = $stripe->prices->create([
            'currency' => config('cashier.currency', 'usd'),
            'lookup_key' => $lookupKey,
            'metadata' => [
                'blendbeats_plan' => $key,
                'blendbeats_type' => config('billing.subscription.default_type', 'dj_membership'),
            ],
            'product' => $product->id,
            'recurring' => [
                'interval' => $this->stripeInterval($tier['billing_interval'] ?? 'monthly'),
            ],
            'unit_amount' => (int) $tier['price_cents'],
        ]);

        return $price->id;
    }

    public function tierForStripePrice(?string $priceId): ?string
    {
        if (! $priceId) {
            return null;
        }

        foreach (config('billing.subscription.tiers', []) as $key => $tier) {
            if (($tier['stripe_price_id'] ?? null) === $priceId) {
                return $key;
            }
        }

        try {
            $price = Cashier::stripe()->prices->retrieve($priceId);
            $lookupKey = $price->lookup_key ?? null;

            foreach (config('billing.subscription.tiers', []) as $key => $tier) {
                if ($lookupKey && ($tier['stripe_lookup_key'] ?? null) === $lookupKey) {
                    return $key;
                }
            }
        } catch (ApiErrorException) {
            return null;
        }

        return null;
    }

    public function freeTier(): string
    {
        return config('billing.subscription.free_tier', 'free');
    }

    private function planPayload(string $key, array $tier, ?User $user): array
    {
        $storageBytes = (int) ($tier['storage_bytes'] ?? 0);
        $priceCents = (int) ($tier['price_cents'] ?? 0);
        $isFree = $key === $this->freeTier();

        return [
            'key' => $key,
            'name' => $tier['name'] ?? str($key)->headline()->toString(),
            'purpose' => $tier['purpose'] ?? null,
            'features' => array_values($tier['features'] ?? []),
            'future_features' => array_values($tier['future_features'] ?? []),
            'storage_bytes' => $storageBytes,
            'storage_label' => $this->formatBytes($storageBytes),
            'advertising_groups' => array_values($tier['advertising_groups'] ?? []),
            'advertising_groups_label' => $this->groupsLabel($tier['advertising_groups'] ?? []),
            'is_free' => $isFree,
            'is_current' => ($user?->media_storage_tier ?: $this->freeTier()) === $key,
            'price_cents' => $priceCents,
            'price_label' => $this->priceLabel($priceCents),
            'interval_label' => $tier['billing_interval'] ?? ($isFree ? 'forever' : 'monthly'),
            'checkout_enabled' => ! $isFree && $priceCents > 0,
        ];
    }

    private function stripeLookupKeyFor(string $key): ?string
    {
        $lookupKey = config("billing.subscription.tiers.{$key}.stripe_lookup_key");

        return is_string($lookupKey) && $lookupKey !== '' ? $lookupKey : null;
    }

    private function stripeInterval(string $interval): string
    {
        return match ($interval) {
            'yearly', 'annual', 'annually', 'year' => 'year',
            default => 'month',
        };
    }

    private function priceLabel(int $priceCents): string
    {
        if ($priceCents <= 0) {
            return '$0';
        }

        return '$'.number_format($priceCents / 100, 2);
    }

    private function groupsLabel(array $groups): string
    {
        if ($groups === []) {
            return 'None';
        }

        if (count($groups) === 1) {
            return 'Group '.reset($groups);
        }

        return 'Groups '.implode('-', [reset($groups), end($groups)]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        return Number::fileSize($bytes);
    }
}
