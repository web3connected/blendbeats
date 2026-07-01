<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BattleEscrow extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_RECORDING = 'recording';
    public const STATUS_VOTING = 'voting';
    public const STATUS_SETTLING = 'settling';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DISPUTED = 'disputed';

    public const MODE_DEMO = 'demo';
    public const MODE_TOKEN = 'token';
    public const MODE_REAL_MONEY = 'real_money';

    protected $fillable = [
        'uuid',
        'battle_id',
        'status',
        'escrow_mode',
        'currency_type',
        'stake_amount',
        'challenger_user_id',
        'opponent_user_id',
        'challenger_lock_transaction_id',
        'opponent_lock_transaction_id',
        'winner_user_id',
        'winner_reward_transaction_id',
        'platform_fee_transaction_id',
        'fan_reward_pool_amount',
        'prize_pool_amount',
        'requires_admin_review',
        'settlement_attempts',
        'last_settlement_error',
        'locked_at',
        'released_at',
        'refunded_at',
        'cancelled_at',
        'disputed_at',
        'expires_at',
        'settled_at',
        'resolved_by_user_id',
        'resolved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stake_amount' => 'integer',
            'fan_reward_pool_amount' => 'integer',
            'prize_pool_amount' => 'integer',
            'requires_admin_review' => 'boolean',
            'settlement_attempts' => 'integer',
            'locked_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'disputed_at' => 'datetime',
            'expires_at' => 'datetime',
            'settled_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BattleEscrow $escrow): void {
            $escrow->uuid ??= (string) Str::uuid();
        });
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(DjBattle::class, 'battle_id');
    }

    public function challengerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_user_id');
    }

    public function opponentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_user_id');
    }

    public function challengerLockTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'challenger_lock_transaction_id');
    }

    public function opponentLockTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'opponent_lock_transaction_id');
    }

    public function winnerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function winnerRewardTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'winner_reward_transaction_id');
    }

    public function platformFeeTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'platform_fee_transaction_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'battle_escrow_id');
    }

    public function isDemoMode(): bool
    {
        return $this->escrow_mode === self::MODE_DEMO;
    }
}
