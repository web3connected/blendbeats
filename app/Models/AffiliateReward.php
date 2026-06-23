<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateReward extends Model
{
    public const TYPE_FUTURE_INCENTIVE = 'future_incentive';

    public const TYPE_AD_CREDIT = 'ad_credit';

    public const TYPE_CASH_COMMISSION = 'cash_commission';

    public const TYPE_POINTS = 'points';

    public const TYPE_MEMBERSHIP_CREDIT = 'membership_credit';

    public const TYPE_MANUAL = 'manual';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'affiliate_account_id',
        'affiliate_referral_id',
        'affiliate_payout_id',
        'reward_type',
        'source',
        'status',
        'amount_cents',
        'currency',
        'points',
        'quantity',
        'membership_credit_days',
        'available_at',
        'expires_at',
        'approved_at',
        'issued_at',
        'paid_at',
        'redeemed_at',
        'cancelled_at',
        'voided_at',
        'issued_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'points' => 'integer',
            'quantity' => 'integer',
            'membership_credit_days' => 'integer',
            'available_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class, 'affiliate_referral_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AffiliateRewardAudit::class);
    }
}
