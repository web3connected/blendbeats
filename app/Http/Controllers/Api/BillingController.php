<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BillingPlanService;
use App\Services\SubscriptionTierSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Exception\ApiErrorException;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingPlanService $plans,
        private readonly SubscriptionTierSyncService $tierSync,
    ) {}

    public function plans(Request $request): JsonResponse
    {
        return response()->json([
            'plans' => $this->plans->plans($request->user()),
            'current_tier' => $request->user()?->media_storage_tier ?? $this->plans->freeTier(),
        ]);
    }

    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTier = $this->tierSync->syncUser($user);
        $subscription = $user->subscription(config('billing.subscription.default_type', 'dj_membership'));

        return response()->json([
            'current_tier' => $currentTier,
            'subscription' => $subscription ? [
                'stripe_status' => $subscription->stripe_status,
                'stripe_price' => $subscription->stripe_price,
                'ends_at' => optional($subscription->ends_at)->toISOString(),
                'on_grace_period' => $subscription->onGracePeriod(),
                'valid' => $subscription->valid(),
            ] : null,
            'has_stripe_customer' => $user->hasStripeId(),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::in(array_keys(config('billing.subscription.tiers', [])))],
        ]);

        $planKey = $validated['plan'];
        $plan = $this->plans->plan($planKey, $request->user());

        abort_if($plan['is_free'] ?? false, 422, 'The free tier does not require checkout.');
        abort_if(((int) ($plan['price_cents'] ?? 0)) <= 0, 422, 'This membership plan does not have a configured price.');

        try {
            $priceId = $this->plans->checkoutPriceIdFor($planKey);

            abort_unless($priceId, 422, 'This membership plan could not be connected to a Stripe test price.');

            $checkout = $request->user()
                ->newSubscription(config('billing.subscription.default_type', 'dj_membership'), $priceId)
                ->checkout([
                    'success_url' => url('/subscription/success?session_id={CHECKOUT_SESSION_ID}'),
                    'cancel_url' => url("/subscription/cancel?plan={$planKey}"),
                    'metadata' => [
                        'blendbeats_plan' => $planKey,
                    ],
                    'subscription_data' => [
                        'metadata' => [
                            'blendbeats_plan' => $planKey,
                        ],
                    ],
                ]);
        } catch (IncompletePayment $exception) {
            report($exception);

            abort(422, 'Stripe needs additional payment confirmation before checkout can continue.');
        } catch (ApiErrorException $exception) {
            report($exception);

            abort(422, $exception->getMessage() ?: 'Stripe checkout could not be started.');
        }

        return response()->json([
            'url' => $checkout->asStripeCheckoutSession()->url,
        ]);
    }

    public function portal(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasStripeId(), 422, 'No Stripe customer exists for this account yet.');

        try {
            $url = $request->user()->billingPortalUrl(url('/subscription'));
        } catch (ApiErrorException $exception) {
            report($exception);

            abort(422, $exception->getMessage() ?: 'Stripe billing portal could not be opened.');
        }

        return response()->json(['url' => $url]);
    }
}
