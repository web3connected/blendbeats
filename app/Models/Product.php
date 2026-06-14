<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const SOURCE_TYPES = [
        'internal',
        'affiliate',
        'external_vendor',
        'print_on_demand',
        'custom_order',
        'marketplace_partner',
    ];

    public const FULFILLMENT_TYPES = [
        'internal',
        'affiliate_redirect',
        'vendor_checkout',
        'print_on_demand',
        'custom_order',
        'marketplace_partner',
    ];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'base_price_cents',
        'sale_price_cents',
        'vendor_name',
        'source_type',
        'external_product_url',
        'affiliate_tracking_url',
        'image_url',
        'category',
        'status',
        'requires_customization',
        'fulfillment_type',
        'commission_rate',
        'customization_schema',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'base_price_cents' => 'integer',
            'sale_price_cents' => 'integer',
            'requires_customization' => 'boolean',
            'commission_rate' => 'decimal:2',
            'customization_schema' => 'array',
            'metadata' => 'array',
        ];
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(ShoppingCartItem::class);
    }

    public function currentPriceCents(): int
    {
        return (int) ($this->sale_price_cents ?? $this->base_price_cents);
    }

    public function requiresExternalCheckout(): bool
    {
        return in_array($this->source_type, ['affiliate', 'external_vendor', 'marketplace_partner'], true)
            || in_array($this->fulfillment_type, ['affiliate_redirect', 'vendor_checkout', 'marketplace_partner'], true);
    }
}
