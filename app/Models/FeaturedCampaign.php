<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeaturedCampaign extends Model
{
    protected $fillable = [
        'featured_slot_group_id',
        'title',
        'description',
        'status',
        'start_date',
        'end_date',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function slotGroup(): BelongsTo
    {
        return $this->belongsTo(FeaturedSlotGroup::class, 'featured_slot_group_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(FeaturedCampaignSlot::class);
    }
}
