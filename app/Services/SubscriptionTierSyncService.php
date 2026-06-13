<?php

namespace App\Services;

use App\Models\User;

class SubscriptionTierSyncService
{
    public function __construct(private readonly BillingPlanService $plans) {}

    public function syncUser(User $user): string
    {
        $tier = $this->activeTierFor($user) ?? $this->plans->freeTier();

        if ($user->media_storage_tier !== $tier) {
            $user->forceFill(['media_storage_tier' => $tier])->save();
        }

        return $tier;
    }

    public function activeTierFor(User $user): ?string
    {
        $user->loadMissing('subscriptions.items');

        foreach ($user->subscriptions as $subscription) {
            if (method_exists($subscription, 'valid') && ! $subscription->valid()) {
                continue;
            }

            $priceIds = collect([$subscription->stripe_price])
                ->merge($subscription->items->pluck('stripe_price'))
                ->filter()
                ->unique();

            foreach ($priceIds as $priceId) {
                $tier = $this->plans->tierForStripePrice($priceId);

                if ($tier) {
                    return $tier;
                }
            }
        }

        return null;
    }
}
