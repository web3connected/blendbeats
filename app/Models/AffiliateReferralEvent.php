<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateReferralEvent extends Model
{
    public const TYPE_SUBSCRIPTION_QUALIFIED = 'subscription_qualified';

    protected $fillable = [
        'affiliate_referral_id',
        'event_type',
        'event_source',
        'target_type',
        'target_id',
        'transaction_type',
        'transaction_id',
        'event_hash',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class, 'affiliate_referral_id');
    }
}
