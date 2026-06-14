<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeaturedSlotGroup extends Model
{
    protected $fillable = [
        'name',
        'group_key',
        'slot_count',
        'template_type',
        'rotation_weight',
        'daily_price_cents',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'slot_count' => 'integer',
            'rotation_weight' => 'integer',
            'daily_price_cents' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(FeaturedCampaign::class);
    }
}
