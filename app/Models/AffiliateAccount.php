<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AffiliateAccount extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_BANNED = 'banned';

    protected $fillable = [
        'user_id',
        'status',
        'display_name',
        'contact_email',
        'joined_at',
        'approved_at',
        'paused_at',
        'banned_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'approved_at' => 'datetime',
            'paused_at' => 'datetime',
            'banned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function rewards(): HasMany
    {
        return $this->hasMany(AffiliateReward::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function defaultReferralCode(): HasOne
    {
        return $this->hasOne(AffiliateReferralCode::class)->where('is_default', true);
    }
}
