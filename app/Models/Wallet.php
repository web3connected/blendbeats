<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Wallet extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'available_balance',
        'locked_balance',
        'lifetime_earned',
        'lifetime_spent',
        'lifetime_withdrawn',
        'status',
    ];

    protected $casts = [
        'available_balance' => 'integer',
        'locked_balance' => 'integer',
        'lifetime_earned' => 'integer',
        'lifetime_spent' => 'integer',
        'lifetime_withdrawn' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Wallet $wallet): void {
            $wallet->uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasAvailableBalance(int $amount): bool
    {
        return $this->available_balance >= $amount;
    }

    public function hasLockedBalance(int $amount): bool
    {
        return $this->locked_balance >= $amount;
    }
}
