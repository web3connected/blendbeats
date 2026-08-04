<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PaymentProvider;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use App\Services\PayPalOrderService;
use App\Services\ShoppingCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class CommerceController extends Controller
{
    public function __construct(
        private readonly ShoppingCartService $carts,
        private readonly PayPalOrderService $paypal,
    ) {}

    public function products(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 0), 0), 24);
        $featuredOnly = $request->boolean('featured');

        $query = Product::query()
            ->where('status', 'active')
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderBy('category')
            ->orderBy('title');

        if ($featuredOnly) {
            $featuredQuery = (clone $query)->where(function ($query): void {
                $query
                    ->where('metadata->featured', true)
                    ->orWhere('metadata->home_featured', true)
                    ->orWhere('metadata->featured', 'true')
                    ->orWhere('metadata->home_featured', 'true')
                    ->orWhere('metadata->featured', 1)
                    ->orWhere('metadata->home_featured', 1);
            });

            if ($limit > 0) {
                $featuredQuery->limit($limit);
            }

            $products = $featuredQuery->get();

            if ($products->isEmpty()) {
                $fallbackQuery = clone $query;

                if ($limit > 0) {
                    $fallbackQuery->limit($limit);
                }

                $products = $fallbackQuery->get();
            }
        } else {
            if ($limit > 0) {
                $query->limit($limit);
            }

            $products = $query->get();
        }

        $products = $products
            ->map(fn (Product $product): array => $this->productPayload($product))
            ->values();

        return response()->json([
            'products' => $products,
            'source_types' => Product::SOURCE_TYPES,
            'fulfillment_types' => Product::FULFILLMENT_TYPES,
        ]);
    }

    public function cart(Request $request): JsonResponse
    {
        $cart = $this->activeCart($request);

        return response()->json([
            'cart' => $this->carts->payload($cart),
        ]);
    }

    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'selected_options' => ['nullable', 'array'],
            'custom_design_data' => ['nullable', 'array'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);
        $selectedOptions = $validated['selected_options'] ?? [];
        $customDesignData = $validated['custom_design_data'] ?? [];

        abort_if(
            $product->requires_customization && empty($selectedOptions) && empty($customDesignData),
            422,
            'This product needs customization details before it can be added to the cart.'
        );

        $cart = $this->activeCart($request);
        $this->carts->addItem($cart, $product, (int) ($validated['quantity'] ?? 1), $selectedOptions, $customDesignData);

        return response()->json([
            'cart' => $this->carts->payload($cart->refresh()),
        ], 201);
    }

    public function updateCartItem(Request $request, ShoppingCartItem $item): JsonResponse
    {
        $cart = $this->activeCart($request);
        abort_unless((int) $item->shopping_cart_id === (int) $cart->id, 403);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $this->carts->updateQuantity($item, (int) $validated['quantity']);

        return response()->json([
            'cart' => $this->carts->payload($cart->refresh()),
        ]);
    }

    public function removeCartItem(Request $request, ShoppingCartItem $item): JsonResponse
    {
        $cart = $this->activeCart($request);
        abort_unless((int) $item->shopping_cart_id === (int) $cart->id, 403);

        $item->delete();

        return response()->json([
            'cart' => $this->carts->payload($cart->refresh()),
        ]);
    }

    public function checkoutSummary(Request $request): JsonResponse
    {
        $cart = $this->activeCart($request);

        return response()->json([
            'cart' => $this->carts->payload($cart),
            'message' => 'Cart items are grouped by fulfillment route. External and affiliate items should redirect to partner checkout; internal items can use platform checkout later.',
        ]);
    }

    public function startPayPalCheckout(Request $request): JsonResponse
    {
        $cart = $this->activeCart($request);
        $items = $cart->items()->with('product')->where('external_checkout_required', false)->get();
        abort_if($items->isEmpty(), 422, 'There are no platform checkout items in this cart.');

        $provider = $this->primaryPayPalProvider();
        $amountCents = (int) $items->sum('estimated_total_cents');
        abort_if($amountCents <= 0, 422, 'The platform checkout total must be greater than zero.');

        try {
            $order = $this->paypal->create($provider, [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'commerce-cart-'.$cart->id,
                    'description' => 'BlendBeats Merch Order',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format($amountCents / 100, 2, '.', ''),
                    ],
                ]],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'brand_name' => 'The Blend Battlegrounds',
                            'landing_page' => 'LOGIN',
                            'shipping_preference' => 'GET_FROM_FILE',
                            'user_action' => 'PAY_NOW',
                            'return_url' => url('/merch?commerce_payment=paypal-return'),
                            'cancel_url' => url('/merch?commerce_payment=cancelled'),
                        ],
                    ],
                ],
            ]);

            $approvalUrl = collect($order['links'] ?? [])->firstWhere('rel', 'payer-action')['href'] ?? null;
            abort_unless(filled($order['id'] ?? null) && filled($approvalUrl), 422, 'PayPal did not return a checkout link.');

            $metadata = $cart->metadata ?? [];
            $metadata['paypal_checkout'] = [
                'order_id' => $order['id'],
                'item_ids' => $items->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'items' => $items->map(fn (ShoppingCartItem $item): array => [
                    'cart_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'title' => $item->product?->title ?? data_get($item->metadata, 'product_title', 'Product'),
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'total_cents' => $item->estimated_total_cents,
                    'selected_options' => $item->selected_options ?? [],
                    'custom_design_data' => $item->custom_design_data ?? [],
                    'fulfillment_type' => $item->fulfillment_type,
                    'vendor_name' => $item->vendor_name,
                ])->values()->all(),
                'amount_cents' => $amountCents,
                'currency' => 'USD',
                'status' => 'created',
                'created_at' => now()->toISOString(),
            ];
            $cart->forceFill(['metadata' => $metadata])->save();

            return response()->json(['approval_url' => $approvalUrl, 'order_id' => $order['id']]);
        } catch (Throwable $exception) {
            Log::error('Merch PayPal checkout could not be started.', [
                'cart_id' => $cart->id,
                'exception' => $exception::class,
            ]);

            abort(422, 'PayPal checkout could not be started. Please try again.');
        }
    }

    public function capturePayPalCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate(['order_id' => ['required', 'string', 'max:255']]);
        $cart = $this->activeCart($request);
        $checkout = data_get($cart->metadata, 'paypal_checkout');

        abort_unless(is_array($checkout) && hash_equals((string) ($checkout['order_id'] ?? ''), $validated['order_id']), 422, 'This PayPal order does not belong to the active cart.');
        abort_unless(($checkout['status'] ?? null) === 'created', 422, 'This PayPal order has already been processed.');

        try {
            $capture = $this->paypal->capture($this->primaryPayPalProvider(), $validated['order_id']);
            $capturedPayment = data_get($capture, 'purchase_units.0.payments.captures.0', []);
            $capturedCents = (int) round(((float) data_get($capturedPayment, 'amount.value', 0)) * 100);
            $currency = data_get($capturedPayment, 'amount.currency_code');

            abort_unless(($capture['status'] ?? null) === 'COMPLETED', 422, 'PayPal has not completed this payment.');
            abort_unless($capturedCents === (int) $checkout['amount_cents'] && $currency === $checkout['currency'], 422, 'The captured PayPal total did not match the cart.');

            DB::transaction(function () use ($cart, $checkout, $capture, $capturedPayment): void {
                $cart->items()->whereIn('id', $checkout['item_ids'] ?? [])->delete();
                $metadata = $cart->metadata ?? [];
                $metadata['paypal_checkout'] = [
                    ...$checkout,
                    'status' => 'completed',
                    'capture_id' => $capturedPayment['id'] ?? null,
                    'payer_email' => data_get($capture, 'payer.email_address'),
                    'shipping' => data_get($capture, 'purchase_units.0.shipping'),
                    'completed_at' => now()->toISOString(),
                ];
                $metadata['completed_payments'][] = $metadata['paypal_checkout'];
                $cart->forceFill(['metadata' => $metadata])->save();
            });

            return response()->json([
                'message' => 'Payment completed. Your merch order has been received.',
                'cart' => $this->carts->payload($cart->refresh()),
            ]);
        } catch (Throwable $exception) {
            Log::error('Merch PayPal capture failed.', [
                'cart_id' => $cart->id,
                'order_id' => $validated['order_id'],
                'exception' => $exception::class,
            ]);

            abort(422, 'PayPal payment could not be confirmed. Your cart was not changed.');
        }
    }

    private function primaryPayPalProvider(): PaymentProvider
    {
        $provider = PaymentProvider::query()
            ->where('provider', 'paypal')
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('display_name')
            ->first();

        abort_unless($provider, 422, 'No active payment provider is configured.');
        abort_unless($provider->provider === 'paypal', 422, "{$provider->display_name} merch checkout is not connected yet.");
        abort_unless($provider->hasEffectiveValueFor('client_id') && $provider->hasEffectiveSecret(), 422, 'PayPal credentials are not ready.');

        return $provider;
    }

    private function activeCart(Request $request): ShoppingCart
    {
        $browserToken = $request->header('X-Commerce-Cart-Token');

        if (! is_string($browserToken) || ! preg_match('/^[A-Za-z0-9-]{20,100}$/', $browserToken)) {
            $browserToken = $request->session()->getId();
        }

        return $this->carts->activeCart($request->user(), $browserToken);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product): array
    {
        $price = $product->currentPriceCents();

        return [
            'id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'description' => $product->description,
            'base_price_cents' => $product->base_price_cents,
            'sale_price_cents' => $product->sale_price_cents,
            'price_cents' => $price,
            'price_label' => $this->carts->money($price),
            'vendor_name' => $product->vendor_name,
            'source_type' => $product->source_type,
            'external_product_url' => $product->external_product_url,
            'affiliate_tracking_url' => $product->affiliate_tracking_url,
            'image_url' => $product->image_url,
            'category' => $product->category,
            'requires_customization' => $product->requires_customization,
            'fulfillment_type' => $product->fulfillment_type,
            'commission_rate' => $product->commission_rate,
            'customization_schema' => $product->customization_schema ?? [],
            'external_checkout_required' => $product->requiresExternalCheckout(),
            'metadata' => $product->metadata ?? [],
        ];
    }
}
