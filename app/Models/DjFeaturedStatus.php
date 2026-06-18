<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DjFeaturedStatus extends Model
{
    protected $table = 'dj_featured_status';

    protected $fillable = [
        'dj_profile_id',
        'slot_number',
        'featured_slot_campaign_option_id',
        'featured_campaign_slot_id',
        'featured_type',
        'rotation_weight',
        'amount_cents',
        'currency',
        'payment_provider',
        'payment_status',
        'payment_reference',
        'claimed_at',
        'payment_metadata',
        'impression_count',
        'click_count',
        'start_date',
        'end_date',
        'pending_payment_notified_at',
        'activated_notified_at',
        'ending_soon_notified_at',
        'expired_notified_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'pending_payment_notified_at' => 'datetime',
            'activated_notified_at' => 'datetime',
            'ending_soon_notified_at' => 'datetime',
            'expired_notified_at' => 'datetime',
            'slot_number' => 'integer',
            'rotation_weight' => 'integer',
            'amount_cents' => 'integer',
            'impression_count' => 'integer',
            'click_count' => 'integer',
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

    public function campaignSlot(): BelongsTo
    {
        return $this->belongsTo(FeaturedCampaignSlot::class, 'featured_campaign_slot_id');
    }

    public function effectiveStartDate(): ?Carbon
    {
        return $this->start_date ?? $this->claimed_at ?? $this->created_at;
    }

    public function effectiveEndDate(): ?Carbon
    {
        if ($this->end_date) {
            return $this->end_date;
        }

        $durationDays = (int) ($this->campaignOption?->duration_days ?? 0);
        $startDate = $this->effectiveStartDate();

        if (! $startDate || $durationDays < 1) {
            return null;
        }

        return $startDate->copy()->addDays($durationDays);
    }

    public function isDisplayableAt(?Carbon $date = null): bool
    {
        $date ??= now();
        $startDate = $this->effectiveStartDate();
        $endDate = $this->effectiveEndDate();

        if ($startDate && $startDate->gt($date)) {
            return false;
        }

        return ! $endDate || $endDate->gt($date);
    }
}
