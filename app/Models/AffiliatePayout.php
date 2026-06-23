<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePayout extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'affiliate_account_id',
        'requested_by_user_id',
        'processed_by_admin_id',
        'status',
        'amount_cents',
        'currency',
        'reward_count',
        'payment_method',
        'payout_reference',
        'requested_at',
        'approved_at',
        'processing_at',
        'paid_at',
        'rejected_at',
        'cancelled_at',
        'rejection_reason',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'reward_count' => 'integer',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'processing_at' => 'datetime',
            'paid_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by_admin_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(AffiliateReward::class);
    }
}
