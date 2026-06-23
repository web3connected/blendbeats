<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AffiliateReferralQualificationService;
use App\Services\SubscriptionTierSyncService;
use Laravel\Cashier\Events\WebhookHandled;

class SyncSubscriptionTierFromStripe
{
    public function __construct(
        private readonly SubscriptionTierSyncService $tierSync,
        private readonly AffiliateReferralQualificationService $referralQualification,
    ) {}

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
            $tier = $this->tierSync->syncUser($user);
            $transactionId = $this->stripeTransactionId($payload);

            if ($this->isSuccessfulSubscriptionEvent($type) && $tier !== config('billing.subscription.free_tier', 'free') && $transactionId) {
                $this->referralQualification->qualifySubscription(
                    user: $user,
                    provider: 'stripe',
                    transactionId: $transactionId,
                    source: 'stripe_webhook:'.$type,
                    planKey: $tier,
                    status: $payload['data']['object']['status'] ?? null,
                );
            }
        }
    }

    private function isSuccessfulSubscriptionEvent(?string $type): bool
    {
        return in_array($type, [
            'checkout.session.completed',
            'customer.subscription.created',
            'customer.subscription.updated',
        ], true);
    }

    private function stripeTransactionId(array $payload): ?string
    {
        $object = $payload['data']['object'] ?? [];

        if (! is_array($object)) {
            return null;
        }

        $subscriptionId = $object['subscription'] ?? null;
        $objectId = $object['id'] ?? null;

        return is_string($subscriptionId) && $subscriptionId !== ''
            ? $subscriptionId
            : (is_string($objectId) && $objectId !== '' ? $objectId : null);
    }
}
