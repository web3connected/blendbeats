<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateReferral extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_REJECTED = 'rejected';

    public const ATTRIBUTION_SIGNUP = 'signup';

    protected $fillable = [
        'affiliate_account_id',
        'affiliate_campaign_id',
        'affiliate_referral_code_id',
        'referred_user_id',
        'affiliate_referral_visit_id',
        'status',
        'attribution_type',
        'attributed_at',
        'qualified_at',
        'qualified_transaction_type',
        'qualified_transaction_id',
        'rejected_at',
        'rejection_reason',
        'is_suspicious',
        'fraud_reason',
        'fraud_flags',
        'fraud_checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attributed_at' => 'datetime',
            'qualified_at' => 'datetime',
            'rejected_at' => 'datetime',
            'is_suspicious' => 'boolean',
            'fraud_flags' => 'array',
            'fraud_checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AffiliateCampaign::class, 'affiliate_campaign_id');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferralCode::class, 'affiliate_referral_code_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function referralVisit(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferralVisit::class, 'affiliate_referral_visit_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AffiliateReferralEvent::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(AffiliateReward::class);
    }
}
