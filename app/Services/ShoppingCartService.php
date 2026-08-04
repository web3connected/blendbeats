<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShoppingCartService
{
    public function activeCart(?User $user, ?string $sessionId): ShoppingCart
    {
        return DB::transaction(function () use ($user, $sessionId): ShoppingCart {
            $identityCarts = collect();

            if ($sessionId) {
                $identityCarts = ShoppingCart::query()
                    ->where('status', 'active')
                    ->where('session_id', $sessionId)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->get();
            }

            $cart = $identityCarts->shift();

            foreach ($identityCarts as $duplicate) {
                $duplicate->items()->update(['shopping_cart_id' => $cart->id]);
                $duplicate->delete();
            }

            $userCart = $user
                ? ShoppingCart::query()
                    ->where('status', 'active')
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first()
                : null;

            if ($cart && $userCart && $cart->isNot($userCart)) {
                $cart->items()->update(['shopping_cart_id' => $userCart->id]);
                $cart->delete();
                $cart = $userCart;
            } elseif (! $cart && $userCart) {
                $cart = $userCart;
            }

            if (! $cart) {
                $cart = ShoppingCart::query()->create([
                    'user_id' => $user?->id,
                    'session_id' => $sessionId,
                    'status' => 'active',
                ]);
            } elseif ($user && ! $cart->user_id) {
                $cart->forceFill(['user_id' => $user->id])->save();
            }

            if ($sessionId && $cart->session_id !== $sessionId) {
                $cart->forceFill(['session_id' => $sessionId])->save();
            }

            $this->consolidateDuplicateItems($cart);

            return $cart;
        });
    }

    /**
     * @param array<string, mixed> $selectedOptions
     * @param array<string, mixed> $customDesignData
     */
    public function addItem(ShoppingCart $cart, Product $product, int $quantity, array $selectedOptions = [], array $customDesignData = []): ShoppingCartItem
    {
        $unitPrice = $product->currentPriceCents();
        $quantity = max(1, min(99, $quantity));

        return DB::transaction(function () use ($cart, $product, $quantity, $selectedOptions, $customDesignData, $unitPrice): ShoppingCartItem {
            $matchingItems = ShoppingCartItem::query()
                ->where('shopping_cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->get()
                ->filter(fn (ShoppingCartItem $item): bool => $this->sameConfiguration($item, $selectedOptions, $customDesignData));

            $item = $matchingItems->shift();

            if ($item) {
                $newQuantity = min(99, (int) $item->quantity + $matchingItems->sum('quantity') + $quantity);
                ShoppingCartItem::query()->whereKey($matchingItems->pluck('id'))->delete();
                $item->forceFill([
                    'quantity' => $newQuantity,
                    'unit_price_cents' => $unitPrice,
                    'estimated_total_cents' => $unitPrice * $newQuantity,
                ])->save();

                return $item->refresh();
            }

            return ShoppingCartItem::query()->create([
                'shopping_cart_id' => $cart->id,
                'product_id' => $product->id,
                'source_type' => $product->source_type,
                'quantity' => $quantity,
                'selected_options' => $selectedOptions ?: null,
                'custom_design_data' => $customDesignData ?: null,
                'unit_price_cents' => $unitPrice,
                'estimated_total_cents' => $unitPrice * $quantity,
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
        });
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

    private function consolidateDuplicateItems(ShoppingCart $cart): void
    {
        $items = $cart->items()->lockForUpdate()->orderBy('id')->get();

        foreach ($items->groupBy(fn (ShoppingCartItem $item): string => $this->lineSignature(
            (int) $item->product_id,
            $item->selected_options ?? [],
            $item->custom_design_data ?? [],
        )) as $matchingItems) {
            if ($matchingItems->count() < 2) {
                continue;
            }

            $item = $matchingItems->shift();
            $quantity = min(99, (int) $item->quantity + $matchingItems->sum('quantity'));
            $item->forceFill([
                'quantity' => $quantity,
                'estimated_total_cents' => $item->unit_price_cents * $quantity,
            ])->save();
            ShoppingCartItem::query()->whereKey($matchingItems->pluck('id'))->delete();
        }
    }

    /** @param array<string, mixed> $selectedOptions @param array<string, mixed> $customDesignData */
    private function sameConfiguration(ShoppingCartItem $item, array $selectedOptions, array $customDesignData): bool
    {
        return $this->lineSignature((int) $item->product_id, $item->selected_options ?? [], $item->custom_design_data ?? [])
            === $this->lineSignature((int) $item->product_id, $selectedOptions, $customDesignData);
    }

    /** @param array<string, mixed> $selectedOptions @param array<string, mixed> $customDesignData */
    private function lineSignature(int $productId, array $selectedOptions, array $customDesignData): string
    {
        return hash('sha256', json_encode([
            'product_id' => $productId,
            'selected_options' => $this->sortRecursively($selectedOptions),
            'custom_design_data' => $this->sortRecursively($customDesignData),
        ], JSON_THROW_ON_ERROR));
    }

    private function sortRecursively(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->sortRecursively($item);
            }
        }
        unset($item);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
