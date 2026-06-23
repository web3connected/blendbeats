<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AffiliateReferralVisit extends Model
{
    protected $fillable = [
        'affiliate_referral_code_id',
        'affiliate_account_id',
        'affiliate_campaign_id',
        'visitor_id',
        'landing_url',
        'referrer_url',
        'ip_hash',
        'user_agent_hash',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'visited_at',
        'converted_user_id',
        'converted_at',
        'is_suspicious',
        'suspicious_reason',
        'suspicious_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'converted_at' => 'datetime',
            'is_suspicious' => 'boolean',
            'suspicious_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferralCode::class, 'affiliate_referral_code_id');
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AffiliateCampaign::class, 'affiliate_campaign_id');
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function referral(): HasOne
    {
        return $this->hasOne(AffiliateReferral::class);
    }
}
