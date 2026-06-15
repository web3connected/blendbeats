<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CommerceProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'title' => 'BlendBeats Digital Sticker Pack',
                'description' => 'Internal digital fan kit for profiles, posts, and promo graphics.',
                'base_price_cents' => 499,
                'vendor_name' => 'BlendBeats',
                'source_type' => 'internal',
                'fulfillment_type' => 'internal',
                'category' => 'Digital',
                'image_url' => null,
                'metadata' => ['lane' => 'internal_checkout', 'home_featured' => true],
            ],
            [
                'title' => 'Battle DJ Hoodie',
                'description' => 'Print-on-demand hoodie with selectable size, color, and custom handle text.',
                'base_price_cents' => 4499,
                'vendor_name' => 'BlendBeats POD',
                'source_type' => 'print_on_demand',
                'fulfillment_type' => 'print_on_demand',
                'category' => 'Apparel',
                'requires_customization' => true,
                'image_url' => null,
                'customization_schema' => [
                    'size' => ['S', 'M', 'L', 'XL', '2XL'],
                    'color' => ['Black', 'Red', 'Gold'],
                    'fields' => ['dj_handle', 'back_text'],
                ],
                'metadata' => ['lane' => 'pod_checkout', 'home_featured' => true],
            ],
            [
                'title' => 'Turntable Slipmat Set',
                'description' => 'Affiliate product routed to a trusted partner checkout with commission tracking.',
                'base_price_cents' => 2999,
                'vendor_name' => 'Partner Gear Shop',
                'source_type' => 'affiliate',
                'fulfillment_type' => 'affiliate_redirect',
                'category' => 'Gear',
                'external_product_url' => 'https://example.com/slipmat-set',
                'affiliate_tracking_url' => 'https://example.com/slipmat-set?ref=blendbeats',
                'commission_rate' => 8.50,
                'image_url' => null,
                'metadata' => ['lane' => 'affiliate_redirect'],
            ],
            [
                'title' => 'Custom Battle Poster',
                'description' => 'Vendor-managed poster where design details are captured before partner fulfillment.',
                'base_price_cents' => 1999,
                'vendor_name' => 'Poster Partner',
                'source_type' => 'external_vendor',
                'fulfillment_type' => 'vendor_checkout',
                'category' => 'Prints',
                'external_product_url' => 'https://example.com/custom-battle-poster',
                'affiliate_tracking_url' => 'https://example.com/custom-battle-poster?ref=blendbeats',
                'requires_customization' => true,
                'commission_rate' => 10.00,
                'image_url' => null,
                'customization_schema' => [
                    'fields' => ['dj_name', 'event_name', 'date'],
                    'sizes' => ['11x17', '18x24'],
                ],
                'metadata' => ['lane' => 'vendor_checkout'],
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['slug' => Str::slug($product['title'])],
                [
                    ...$product,
                    'slug' => Str::slug($product['title']),
                    'status' => 'active',
                ],
            );
        }
    }
}
