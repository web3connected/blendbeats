<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeaturedSlotCampaignOption extends Model
{
    protected $fillable = [
        'name',
        'description',
        'duration_days',
        'price_cents',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price_cents' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function featuredStatuses(): HasMany
    {
        return $this->hasMany(DjFeaturedStatus::class);
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->price_cents === null) {
            return 'No price set';
        }

        return '$'.number_format($this->price_cents / 100, 2);
    }
}
