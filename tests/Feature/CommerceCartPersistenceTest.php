<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceCartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cart_remains_visible_when_header_cart_refreshes(): void
    {
        $product = $this->product('Persistent Shirt');
        $token = 'browser-cart-token-1234567890';

        $this->withHeader('X-Commerce-Cart-Token', $token)
            ->postJson('/api/commerce/cart/items', ['product_id' => $product->id])
            ->assertCreated()
            ->assertJsonPath('cart.item_count', 1);

        $this->withHeader('X-Commerce-Cart-Token', $token)
            ->getJson('/api/commerce/cart')
            ->assertOk()
            ->assertJsonPath('cart.item_count', 1)
            ->assertJsonPath('cart.items.0.product_id', $product->id);

        $this->assertDatabaseCount('shopping_carts', 1);
    }

    public function test_adding_same_product_configuration_increases_quantity_on_one_line(): void
    {
        $product = $this->product('Quantity Shirt');
        $token = 'browser-cart-token-quantity-12345';
        $payload = [
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => ['color' => 'Black', 'size' => 'L'],
        ];

        $this->withHeader('X-Commerce-Cart-Token', $token)
            ->postJson('/api/commerce/cart/items', $payload)
            ->assertCreated()
            ->assertJsonPath('cart.item_count', 1);

        $this->withHeader('X-Commerce-Cart-Token', $token)
            ->postJson('/api/commerce/cart/items', [
                ...$payload,
                'selected_options' => ['size' => 'L', 'color' => 'Black'],
            ])
            ->assertCreated()
            ->assertJsonPath('cart.item_count', 2)
            ->assertJsonCount(1, 'cart.items')
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.items.0.estimated_total_label', '$30.00');

        $this->assertDatabaseCount('shopping_cart_items', 1);
    }

    public function test_same_product_with_different_options_remains_separate_lines(): void
    {
        $product = $this->product('Sized Shirt');
        $token = 'browser-cart-token-options-12345';

        foreach (['M', 'XL'] as $size) {
            $this->withHeader('X-Commerce-Cart-Token', $token)
                ->postJson('/api/commerce/cart/items', [
                    'product_id' => $product->id,
                    'selected_options' => ['size' => $size],
                ])->assertCreated();
        }

        $this->withHeader('X-Commerce-Cart-Token', $token)
            ->getJson('/api/commerce/cart')
            ->assertOk()
            ->assertJsonCount(2, 'cart.items')
            ->assertJsonPath('cart.item_count', 2);
    }

    public function test_existing_duplicate_lines_are_healed_when_cart_is_opened(): void
    {
        $product = $this->product('Existing Duplicate');
        $token = 'browser-cart-token-heal-12345678';
        $cart = ShoppingCart::query()->create(['session_id' => $token, 'status' => 'active']);
        $this->cartItem($cart, $product);
        $this->cartItem($cart, $product);

        $this->withHeader('X-Commerce-Cart-Token', $token)
            ->getJson('/api/commerce/cart')
            ->assertOk()
            ->assertJsonCount(1, 'cart.items')
            ->assertJsonPath('cart.items.0.quantity', 2);

        $this->assertDatabaseCount('shopping_cart_items', 1);
    }

    public function test_duplicate_browser_carts_are_consolidated_instead_of_hiding_items(): void
    {
        $productA = $this->product('First Product');
        $productB = $this->product('Second Product');
        $token = 'browser-cart-token-duplicate-12345';
        $first = ShoppingCart::query()->create(['session_id' => $token, 'status' => 'active']);
        $second = ShoppingCart::query()->create(['session_id' => $token, 'status' => 'active']);
        $this->cartItem($first, $productA);
        $this->cartItem($second, $productB);

        $this->withHeader('X-Commerce-Cart-Token', $token)
            ->getJson('/api/commerce/cart')
            ->assertOk()
            ->assertJsonPath('cart.item_count', 2);

        $this->assertDatabaseCount('shopping_carts', 1);
        $this->assertDatabaseCount('shopping_cart_items', 2);
    }

    public function test_guest_cart_is_merged_into_existing_user_cart_after_login(): void
    {
        $guestProduct = $this->product('Guest Product');
        $userProduct = $this->product('User Product');
        $token = 'browser-cart-token-login-123456';

        $this->withHeader('X-Commerce-Cart-Token', $token)
            ->postJson('/api/commerce/cart/items', ['product_id' => $guestProduct->id])
            ->assertCreated();

        $user = User::factory()->create();
        $userCart = ShoppingCart::query()->create(['user_id' => $user->id, 'status' => 'active']);
        $this->cartItem($userCart, $userProduct);

        $this->actingAs($user)
            ->withHeader('X-Commerce-Cart-Token', $token)
            ->getJson('/api/commerce/cart')
            ->assertOk()
            ->assertJsonPath('cart.item_count', 2);

        $this->assertDatabaseCount('shopping_carts', 1);
        $this->assertSame($user->id, ShoppingCart::query()->sole()->user_id);
    }

    private function product(string $title): Product
    {
        return Product::query()->create([
            'title' => $title,
            'slug' => str($title)->slug(),
            'base_price_cents' => 1500,
            'source_type' => 'internal',
            'fulfillment_type' => 'internal',
            'status' => 'active',
        ]);
    }

    private function cartItem(ShoppingCart $cart, Product $product): ShoppingCartItem
    {
        return ShoppingCartItem::query()->create([
            'shopping_cart_id' => $cart->id,
            'product_id' => $product->id,
            'source_type' => 'internal',
            'quantity' => 1,
            'unit_price_cents' => 1500,
            'estimated_total_cents' => 1500,
            'external_checkout_required' => false,
            'fulfillment_type' => 'internal',
        ]);
    }
}
