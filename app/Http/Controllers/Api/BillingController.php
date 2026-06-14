<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentProvider;
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
            'payment_profile' => $this->paymentProfile(),
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
            'payment_profile' => $this->paymentProfile(),
        ]);
    }

    public function paymentMethods(): JsonResponse
    {
        $providers = PaymentProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('display_name')
            ->get()
            ->map(fn (PaymentProvider $provider): array => [
                'id' => $provider->id,
                'provider' => $provider->provider,
                'display_name' => $provider->display_name,
                'mode' => $provider->mode,
                'is_primary' => $provider->is_primary,
                'supported_features' => $provider->supported_features ?? [],
                'linking_enabled' => false,
                'is_linked' => false,
                'status_label' => 'Available',
            ]);

        return response()->json([
            'payment_methods' => $providers,
            'payment_profile' => $this->paymentProfile(),
        ]);
    }

    private function paymentProfile(): array
    {
        $activeProviders = PaymentProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('display_name')
            ->get();
        $primaryProvider = $activeProviders->firstWhere('is_primary', true) ?? $activeProviders->first();

        return [
            'primary_provider' => $primaryProvider ? [
                'provider' => $primaryProvider->provider,
                'display_name' => $primaryProvider->display_name,
                'mode' => $primaryProvider->mode,
                'is_primary' => $primaryProvider->is_primary,
                'supported_features' => $primaryProvider->supported_features ?? [],
                'credentials_ready' => $primaryProvider->hasEffectiveValueFor('client_id') && $primaryProvider->hasEffectiveSecret(),
                'checkout_ready' => $primaryProvider->provider === 'stripe' && $primaryProvider->hasEffectiveValueFor('client_id') && $primaryProvider->hasEffectiveSecret(),
            ] : null,
            'active_providers' => $activeProviders
                ->map(fn (PaymentProvider $provider): array => [
                    'provider' => $provider->provider,
                    'display_name' => $provider->display_name,
                    'mode' => $provider->mode,
                    'is_primary' => $provider->is_primary,
                    'supported_features' => $provider->supported_features ?? [],
                    'credentials_ready' => $provider->hasEffectiveValueFor('client_id') && $provider->hasEffectiveSecret(),
                    'checkout_ready' => $provider->provider === 'stripe' && $provider->hasEffectiveValueFor('client_id') && $provider->hasEffectiveSecret(),
                ])
                ->values(),
        ];
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
            $primaryProvider = $this->paymentProfile()['primary_provider'];
            abort_unless($primaryProvider, 422, 'No active payment provider is configured.');
            abort_unless($primaryProvider['provider'] === 'stripe', 422, "{$primaryProvider['display_name']} subscription checkout is not connected yet.");

            $priceId = $this->plans->checkoutPriceIdFor($planKey);

            abort_unless($priceId, 422, 'This membership plan could not be connected to the active checkout price.');

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

            abort(422, 'The payment provider needs additional confirmation before checkout can continue.');
        } catch (ApiErrorException $exception) {
            report($exception);

            abort(422, $exception->getMessage() ?: 'Checkout could not be started.');
        }

        return response()->json([
            'url' => $checkout->asStripeCheckoutSession()->url,
        ]);
    }

    public function portal(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasStripeId(), 422, 'No billing customer exists for this account yet.');

        try {
            $url = $request->user()->billingPortalUrl(url('/subscription'));
        } catch (ApiErrorException $exception) {
            report($exception);

            abort(422, $exception->getMessage() ?: 'The billing portal could not be opened.');
        }

        return response()->json(['url' => $url]);
    }
}
