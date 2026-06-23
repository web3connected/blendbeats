<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateRewardAudit extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_ISSUED = 'issued';

    public const ACTION_PAID = 'paid';

    public const ACTION_REDEEMED = 'redeemed';

    public const ACTION_EXPIRED = 'expired';

    public const ACTION_CANCELLED = 'cancelled';

    public const ACTION_VOIDED = 'voided';

    public const ACTION_STATUS_CHANGED = 'status_changed';

    protected $fillable = [
        'affiliate_reward_id',
        'action',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
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

    public function reward(): BelongsTo
    {
        return $this->belongsTo(AffiliateReward::class, 'affiliate_reward_id');
    }
}
