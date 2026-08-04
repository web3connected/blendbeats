<?php

namespace Tests\Feature;

use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommercePayPalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.paypal.mode' => 'sandbox',
            'billing.paypal.client_id' => 'sandbox-client',
            'billing.paypal.secret' => 'sandbox-secret',
        ]);

        PaymentProvider::query()->updateOrCreate(['provider' => 'paypal'], [
            'display_name' => 'PayPal',
            'mode' => 'sandbox',
            'is_active' => true,
            'is_primary' => true,
            'supported_features' => ['checkout'],
        ]);
    }

    public function test_platform_merch_items_can_be_paid_with_paypal_while_partner_items_remain(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'access-token']),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-123',
                'status' => 'CREATED',
                'links' => [[
                    'rel' => 'payer-action',
                    'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-123',
                ]],
            ]),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-123/capture' => Http::response([
                'id' => 'ORDER-123',
                'status' => 'COMPLETED',
                'payer' => ['email_address' => 'buyer@example.com'],
                'purchase_units' => [[
                    'shipping' => ['name' => ['full_name' => 'Test Buyer']],
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-123',
                        'amount' => ['currency_code' => 'USD', 'value' => '15.00'],
                    ]]],
                ]],
            ]),
        ]);

        $platform = $this->product('Platform Shirt', 1500);
        $partner = $this->product('Partner Gear', 2500, true);

        $this->postJson('/api/commerce/cart/items', ['product_id' => $platform->id])->assertCreated();
        $this->postJson('/api/commerce/cart/items', ['product_id' => $partner->id])->assertCreated();

        $this->postJson('/api/commerce/checkout/paypal')
            ->assertOk()
            ->assertJsonPath('order_id', 'ORDER-123')
            ->assertJsonPath('approval_url', 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-123');

        $cart = ShoppingCart::query()->sole();
        $this->assertSame(1500, data_get($cart->fresh()->metadata, 'paypal_checkout.amount_cents'));

        $this->postJson('/api/commerce/checkout/paypal/capture', ['order_id' => 'ORDER-123'])
            ->assertOk()
            ->assertJsonPath('cart.item_count', 1)
            ->assertJsonPath('cart.items.0.product_id', $partner->id);

        $this->assertDatabaseMissing('shopping_cart_items', ['product_id' => $platform->id]);
        $this->assertDatabaseHas('shopping_cart_items', ['product_id' => $partner->id]);
        $this->assertSame('completed', data_get($cart->fresh()->metadata, 'paypal_checkout.status'));
        $this->assertSame('CAPTURE-123', data_get($cart->fresh()->metadata, 'paypal_checkout.capture_id'));
        $this->assertSame('Platform Shirt', data_get($cart->fresh()->metadata, 'completed_payments.0.items.0.title'));
        $this->assertSame('buyer@example.com', data_get($cart->fresh()->metadata, 'completed_payments.0.payer_email'));

        Http::assertSent(fn ($request) => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
            && $request['purchase_units'][0]['amount']['value'] === '15.00'
            && $request['payment_source']['paypal']['experience_context']['shipping_preference'] === 'GET_FROM_FILE');
    }

    public function test_capture_total_mismatch_preserves_cart_items(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'access-token']),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-MISMATCH',
                'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']],
            ]),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-MISMATCH/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [[
                    'id' => 'CAPTURE-MISMATCH',
                    'amount' => ['currency_code' => 'USD', 'value' => '1.00'],
                ]]]]],
            ]),
        ]);

        $product = $this->product('Platform Hoodie', 4500);
        $this->postJson('/api/commerce/cart/items', ['product_id' => $product->id])->assertCreated();
        $this->postJson('/api/commerce/checkout/paypal')->assertOk();

        $this->postJson('/api/commerce/checkout/paypal/capture', ['order_id' => 'ORDER-MISMATCH'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'PayPal payment could not be confirmed. Your cart was not changed.');

        $this->assertDatabaseHas('shopping_cart_items', ['product_id' => $product->id]);
    }

    public function test_checkout_requires_platform_items_and_ready_paypal_provider(): void
    {
        $this->actingAs(User::factory()->create());
        $partner = $this->product('External Product', 1200, true);
        $this->postJson('/api/commerce/cart/items', ['product_id' => $partner->id])->assertCreated();
        $this->postJson('/api/commerce/checkout/paypal')
            ->assertStatus(422)
            ->assertJsonPath('message', 'There are no platform checkout items in this cart.');
    }

    private function product(string $title, int $priceCents, bool $external = false): Product
    {
        return Product::query()->create([
            'title' => $title,
            'slug' => str($title)->slug(),
            'description' => 'Test product',
            'base_price_cents' => $priceCents,
            'vendor_name' => $external ? 'Partner' : 'BlendBeats',
            'source_type' => $external ? 'affiliate' : 'internal',
            'external_product_url' => $external ? 'https://partner.test/product' : null,
            'affiliate_tracking_url' => $external ? 'https://partner.test/product?ref=blendbeats' : null,
            'category' => 'Merch',
            'status' => 'active',
            'fulfillment_type' => $external ? 'affiliate_redirect' : 'internal',
        ]);
    }
}
