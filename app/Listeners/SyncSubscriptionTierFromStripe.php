<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\SubscriptionTierSyncService;
use Laravel\Cashier\Events\WebhookHandled;

class SyncSubscriptionTierFromStripe
{
    public function __construct(private readonly SubscriptionTierSyncService $tierSync) {}

    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;
        $type = $payload['type'] ?? null;

        if (! in_array($type, [
            'checkout.session.completed',
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'invoice.payment_failed',
        ], true)) {
            return;
        }

        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        $user = User::where('stripe_id', $customerId)->first();

        if ($user) {
            $this->tierSync->syncUser($user);
        }
    }
}
