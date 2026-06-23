<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayPalWebhookEvent;
use App\Models\User;
use App\Services\AffiliateReferralQualificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function __construct(private readonly AffiliateReferralQualificationService $referralQualification)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $eventType = $request->input('event_type');
        $resourceId = $request->input('resource.id');

        $webhookEvent = PayPalWebhookEvent::create([
            'event_type' => $eventType,
            'resource_id' => $resourceId,
            'payload' => $request->all(),
        ]);

        $this->syncSubscriptionStatus($eventType, $resourceId);

        $webhookEvent->update([
            'processed_at' => now(),
        ]);

        Log::info('PayPal webhook received', [
            'event_type' => $eventType,
            'resource_id' => $resourceId,
        ]);

        return response()->json([
            'received' => true,
        ]);
    }

    private function syncSubscriptionStatus(?string $eventType, ?string $resourceId): void
    {
        if (! $eventType || ! $resourceId) {
            return;
        }

        $status = match ($eventType) {
            'BILLING.SUBSCRIPTION.ACTIVATED',
            'BILLING.SUBSCRIPTION.RE-ACTIVATED' => 'active',
            'BILLING.SUBSCRIPTION.CANCELLED' => 'cancelled',
            'BILLING.SUBSCRIPTION.SUSPENDED' => 'suspended',
            'BILLING.SUBSCRIPTION.EXPIRED' => 'expired',
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => 'payment_failed',
            default => null,
        };

        if (! $status) {
            return;
        }

        $user = User::where('paypal_subscription_id', $resourceId)->first();

        if (! $user) {
            return;
        }

        if ($user->billing_provider !== 'paypal') {
            return;
        }

        $updates = [
            'paypal_subscription_status' => $status,
        ];

        if ($status === 'active') {
            $updates['media_storage_tier'] = 'dj_plus';
        } elseif ($status !== 'payment_failed') {
            $updates['media_storage_tier'] = 'free';
        }

        $user->forceFill($updates)->save();

        if ($status === 'active') {
            $this->referralQualification->qualifySubscription(
                user: $user,
                provider: 'paypal',
                transactionId: $resourceId,
                source: 'paypal_webhook:'.$eventType,
                planKey: 'dj_plus',
                status: $status,
            );
        }
    }
}
