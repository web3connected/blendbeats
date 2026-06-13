<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Number;

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

        return null;
    }

    public function freeTier(): string
    {
        return config('billing.subscription.free_tier', 'free');
    }

    private function planPayload(string $key, array $tier, ?User $user): array
    {
        $priceId = $this->stripePriceIdFor($key);
        $storageBytes = (int) ($tier['storage_bytes'] ?? 0);
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
            'price_label' => $isFree ? '$0' : ($priceId ? 'Stripe test price' : 'Setup needed'),
            'interval_label' => $isFree ? 'forever' : 'monthly',
            'checkout_enabled' => ! $isFree && $priceId !== null,
        ];
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
