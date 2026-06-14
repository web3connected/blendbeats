<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingCartItem extends Model
{
    protected $fillable = [
        'shopping_cart_id',
        'product_id',
        'source_type',
        'quantity',
        'selected_options',
        'custom_design_data',
        'unit_price_cents',
        'estimated_total_cents',
        'vendor_name',
        'external_checkout_required',
        'affiliate_tracking_url',
        'fulfillment_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'selected_options' => 'array',
            'custom_design_data' => 'array',
            'unit_price_cents' => 'integer',
            'estimated_total_cents' => 'integer',
            'external_checkout_required' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ShoppingCart::class, 'shopping_cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
