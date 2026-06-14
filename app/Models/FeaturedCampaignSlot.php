<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FeaturedCampaignSlot extends Model
{
    protected $fillable = [
        'featured_campaign_id',
        'group_slot_number',
        'claim_status',
        'claimed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'group_slot_number' => 'integer',
            'claimed_by_user_id' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FeaturedCampaign::class, 'featured_campaign_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function featuredStatus(): HasOne
    {
        return $this->hasOne(DjFeaturedStatus::class);
    }

    public function featuredStatuses(): HasMany
    {
        return $this->hasMany(DjFeaturedStatus::class);
    }
}
