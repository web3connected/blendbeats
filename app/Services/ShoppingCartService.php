<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use App\Models\User;
use Illuminate\Support\Collection;

class ShoppingCartService
{
    public function activeCart(?User $user, ?string $sessionId): ShoppingCart
    {
        $query = ShoppingCart::query()->where('status', 'active');

        if ($user) {
            $cart = (clone $query)->where('user_id', $user->id)->latest()->first();

            if ($cart) {
                return $cart;
            }
        }

        if ($sessionId) {
            $cart = (clone $query)->where('session_id', $sessionId)->latest()->first();

            if ($cart) {
                if ($user && ! $cart->user_id) {
                    $cart->forceFill(['user_id' => $user->id])->save();
                }

                return $cart;
            }
        }

        return ShoppingCart::query()->create([
            'user_id' => $user?->id,
            'session_id' => $sessionId,
            'status' => 'active',
        ]);
    }

    /**
     * @param array<string, mixed> $selectedOptions
     * @param array<string, mixed> $customDesignData
     */
    public function addItem(ShoppingCart $cart, Product $product, int $quantity, array $selectedOptions = [], array $customDesignData = []): ShoppingCartItem
    {
        $unitPrice = $product->currentPriceCents();

        return ShoppingCartItem::query()->create([
            'shopping_cart_id' => $cart->id,
            'product_id' => $product->id,
            'source_type' => $product->source_type,
            'quantity' => max(1, min(99, $quantity)),
            'selected_options' => $selectedOptions ?: null,
            'custom_design_data' => $customDesignData ?: null,
            'unit_price_cents' => $unitPrice,
            'estimated_total_cents' => $unitPrice * max(1, min(99, $quantity)),
            'vendor_name' => $product->vendor_name,
            'external_checkout_required' => $product->requiresExternalCheckout(),
            'affiliate_tracking_url' => $product->affiliate_tracking_url ?: $product->external_product_url,
            'fulfillment_type' => $product->fulfillment_type,
            'metadata' => [
                'product_title' => $product->title,
                'commission_rate' => $product->commission_rate,
                'requires_customization' => $product->requires_customization,
            ],
        ]);
    }

    public function updateQuantity(ShoppingCartItem $item, int $quantity): ShoppingCartItem
    {
        $quantity = max(1, min(99, $quantity));

        $item->forceFill([
            'quantity' => $quantity,
            'estimated_total_cents' => $item->unit_price_cents * $quantity,
        ])->save();

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ShoppingCart $cart): array
    {
        $cart->loadMissing('items.product');
        $items = $cart->items->map(fn (ShoppingCartItem $item): array => $this->itemPayload($item))->values();
        $groups = $this->checkoutGroups($cart->items);

        return [
            'id' => $cart->id,
            'status' => $cart->status,
            'items' => $items,
            'item_count' => (int) $cart->items->sum('quantity'),
            'estimated_total_cents' => (int) $cart->items->sum('estimated_total_cents'),
            'estimated_total_label' => $this->money((int) $cart->items->sum('estimated_total_cents')),
            'checkout_groups' => $groups,
        ];
    }

    /**
     * @param Collection<int, ShoppingCartItem> $items
     * @return array<string, array<string, mixed>>
     */
    public function checkoutGroups(Collection $items): array
    {
        $grouped = [
            'internal' => ['label' => 'Internal Checkout Items', 'items' => [], 'total_cents' => 0],
            'affiliate_redirect' => ['label' => 'Affiliate Redirect Items', 'items' => [], 'total_cents' => 0],
            'print_on_demand' => ['label' => 'Print-On-Demand Items', 'items' => [], 'total_cents' => 0],
            'vendor_checkout' => ['label' => 'Vendor Checkout Items', 'items' => [], 'total_cents' => 0],
            'custom_order' => ['label' => 'Custom Order Items', 'items' => [], 'total_cents' => 0],
            'marketplace_partner' => ['label' => 'Marketplace Partner Items', 'items' => [], 'total_cents' => 0],
        ];

        foreach ($items as $item) {
            $key = $item->fulfillment_type;
            if (! isset($grouped[$key])) {
                $key = $item->external_checkout_required ? 'vendor_checkout' : 'internal';
            }

            $grouped[$key]['items'][] = $this->itemPayload($item);
            $grouped[$key]['total_cents'] += $item->estimated_total_cents;
        }

        return collect($grouped)
            ->map(function (array $group): array {
                $group['item_count'] = count($group['items']);
                $group['total_label'] = $this->money((int) $group['total_cents']);

                return $group;
            })
            ->filter(fn (array $group): bool => $group['item_count'] > 0)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function itemPayload(ShoppingCartItem $item): array
    {
        $product = $item->product;

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'title' => $product?->title ?? ($item->metadata['product_title'] ?? 'Product'),
            'image_url' => $product?->image_url,
            'source_type' => $item->source_type,
            'quantity' => $item->quantity,
            'selected_options' => $item->selected_options ?? [],
            'custom_design_data' => $item->custom_design_data ?? [],
            'unit_price_cents' => $item->unit_price_cents,
            'unit_price_label' => $this->money($item->unit_price_cents),
            'estimated_total_cents' => $item->estimated_total_cents,
            'estimated_total_label' => $this->money($item->estimated_total_cents),
            'vendor_name' => $item->vendor_name,
            'external_checkout_required' => $item->external_checkout_required,
            'affiliate_tracking_url' => $item->affiliate_tracking_url,
            'fulfillment_type' => $item->fulfillment_type,
            'metadata' => $item->metadata ?? [],
        ];
    }

    public function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
