<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAdCredit extends Model
{
    protected $fillable = [
        'user_id',
        'credit_type',
        'source',
        'code',
        'duration_days',
        'quantity',
        'remaining_quantity',
        'discount_type',
        'discount_value',
        'status',
        'granted_at',
        'expires_at',
        'redeemed_at',
        'notified_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'quantity' => 'integer',
            'remaining_quantity' => 'integer',
            'discount_value' => 'integer',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'notified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
