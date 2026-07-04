<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DjBookingRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_NEEDS_DISCUSSION = 'needs_discussion';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PENDING_EXTERNAL = 'pending_external_payment';
    public const PAYMENT_PAID_EXTERNAL = 'paid_external';
    public const PAYMENT_REFUNDED_EXTERNAL = 'refunded_external';
    public const PAYMENT_NOT_REQUIRED = 'not_required';

    public const ACTIVE_CALENDAR_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_NEEDS_DISCUSSION,
        self::STATUS_ACCEPTED,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'uuid',
        'dj_profile_id',
        'dj_user_id',
        'requested_by_user_id',
        'event_name',
        'event_type',
        'event_date',
        'start_time',
        'end_time',
        'timezone',
        'location_name',
        'location_address',
        'city',
        'state',
        'postal_code',
        'country',
        'expected_crowd_size',
        'music_style',
        'requested_services',
        'message',
        'hourly_rate_tokens',
        'hourly_rate_amount',
        'estimated_hours',
        'estimated_total_amount',
        'currency',
        'contact_name',
        'contact_email',
        'contact_phone',
        'status',
        'payment_status',
        'accepted_at',
        'declined_at',
        'cancelled_at',
        'completed_at',
        'paid_at',
        'internal_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'requested_services' => 'array',
            'hourly_rate_tokens' => 'integer',
            'hourly_rate_amount' => 'decimal:2',
            'estimated_hours' => 'decimal:2',
            'estimated_total_amount' => 'decimal:2',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DjBookingRequest $booking): void {
            $booking->uuid ??= (string) Str::uuid();
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'dj_profile_id');
    }

    public function djUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dj_user_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function scopeForDjUser(Builder $query, int $userId): Builder
    {
        return $query->where('dj_user_id', $userId);
    }

    public function scopeCalendarVisible(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_CALENDAR_STATUSES);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_DECLINED,
            self::STATUS_CANCELLED,
            self::STATUS_COMPLETED,
            self::STATUS_EXPIRED,
        ], true);
    }
}
