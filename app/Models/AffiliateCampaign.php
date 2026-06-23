<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AffiliateCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'description',
        'starts_at',
        'ends_at',
        'created_by_admin_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function referralCodes(): HasMany
    {
        return $this->hasMany(AffiliateReferralCode::class);
    }

    public function referralVisits(): HasMany
    {
        return $this->hasMany(AffiliateReferralVisit::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function qualifiedReferrals(): HasMany
    {
        return $this->referrals()->where('status', AffiliateReferral::STATUS_QUALIFIED);
    }

    public function rewards(): HasManyThrough
    {
        return $this->hasManyThrough(
            AffiliateReward::class,
            AffiliateReferral::class,
            'affiliate_campaign_id',
            'affiliate_referral_id',
            'id',
            'id',
        );
    }
}
