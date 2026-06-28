<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'uuid',
        'wallet_id',
        'user_id',
        'type',
        'direction',
        'status',
        'amount',
        'balance_before',
        'balance_after',
        'locked_balance_before',
        'locked_balance_after',
        'related_type',
        'related_id',
        'description',
        'metadata',
        'created_by_user_id',
        'completed_at',
        'failed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'locked_balance_before' => 'integer',
        'locked_balance_after' => 'integer',
        'metadata' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
