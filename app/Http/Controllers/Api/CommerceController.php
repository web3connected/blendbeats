<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ShoppingCartItem;
use App\Services\ShoppingCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommerceController extends Controller
{
    public function __construct(private readonly ShoppingCartService $carts) {}

    public function products(Request $request): JsonResponse
    {
        $products = Product::query()
            ->where('status', 'active')
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderBy('category')
            ->orderBy('title')
            ->get()
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
        $cart = $this->carts->activeCart($request->user(), $request->session()->getId());

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

        $cart = $this->carts->activeCart($request->user(), $request->session()->getId());
        $this->carts->addItem($cart, $product, (int) ($validated['quantity'] ?? 1), $selectedOptions, $customDesignData);

        return response()->json([
            'cart' => $this->carts->payload($cart->refresh()),
        ], 201);
    }

    public function updateCartItem(Request $request, ShoppingCartItem $item): JsonResponse
    {
        $cart = $this->carts->activeCart($request->user(), $request->session()->getId());
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
        $cart = $this->carts->activeCart($request->user(), $request->session()->getId());
        abort_unless((int) $item->shopping_cart_id === (int) $cart->id, 403);

        $item->delete();

        return response()->json([
            'cart' => $this->carts->payload($cart->refresh()),
        ]);
    }

    public function checkoutSummary(Request $request): JsonResponse
    {
        $cart = $this->carts->activeCart($request->user(), $request->session()->getId());

        return response()->json([
            'cart' => $this->carts->payload($cart),
            'message' => 'Cart items are grouped by fulfillment route. External and affiliate items should redirect to partner checkout; internal items can use platform checkout later.',
        ]);
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
