<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjBookingSetting extends Model
{
    protected $fillable = [
        'dj_profile_id',
        'available_for_bookings',
        'booking_email',
        'show_booking_email',
        'rate_type',
        'minimum_rate_cents',
        'currency',
        'travel_radius_miles',
        'will_travel',
        'available_for',
        'technical_rider_notes',
    ];

    protected function casts(): array
    {
        return [
            'available_for_bookings' => 'boolean',
            'show_booking_email' => 'boolean',
            'minimum_rate_cents' => 'integer',
            'travel_radius_miles' => 'integer',
            'will_travel' => 'boolean',
            'available_for' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DjProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'dj_profile_id');
    }
}
