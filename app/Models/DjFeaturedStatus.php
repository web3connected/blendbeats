<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjFeaturedStatus extends Model
{
    protected $table = 'dj_featured_status';

    protected $fillable = [
        'dj_profile_id',
        'slot_number',
        'featured_slot_campaign_option_id',
        'featured_type',
        'rotation_weight',
        'amount_cents',
        'currency',
        'payment_provider',
        'payment_status',
        'payment_reference',
        'claimed_at',
        'payment_metadata',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'slot_number' => 'integer',
            'rotation_weight' => 'integer',
            'amount_cents' => 'integer',
            'claimed_at' => 'datetime',
            'payment_metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DjProfile, $this>
     */
    public function djProfile(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class);
    }

    public function campaignOption(): BelongsTo
    {
        return $this->belongsTo(FeaturedSlotCampaignOption::class, 'featured_slot_campaign_option_id');
    }
}
