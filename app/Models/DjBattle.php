<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class DjBattle extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_RECORDING = 'recording';

    public const STATUS_VOTING = 'voting';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DISPUTED = 'disputed';

    public const SAMPLE_PACK_PENDING = 'pending';

    public const SAMPLE_PACK_READY = 'ready';

    public const SAMPLE_PACK_BYPASSED = 'bypassed';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAUSED,
        self::STATUS_ACCEPTED,
        self::STATUS_RECORDING,
        self::STATUS_VOTING,
        self::STATUS_DISPUTED,
    ];

    protected $fillable = [
        'uuid',
        'challenger_dj_profile_id',
        'opponent_dj_profile_id',
        'created_by_user_id',
        'battle_type',
        'status',
        'title',
        'theme',
        'description',
        'rules',
        'duration_seconds',
        'voting_duration_hours',
        'minimum_votes',
        'stake_amount',
        'currency',
        'sample_pack_status',
        'sample_pack_ready_at',
        'sample_pack_bypassed_at',
        'sample_pack_metadata',
        'fan_reward_pool_amount',
        'prize_pool_amount',
        'winner_dj_profile_id',
        'challenge_message',
        'accepted_at',
        'response_due_at',
        'ready_due_at',
        'challenger_ready_at',
        'opponent_ready_at',
        'recording_started_at',
        'recording_ends_at',
        'voting_started_at',
        'voting_ends_at',
        'completed_at',
        'declined_at',
        'cancelled_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'voting_duration_hours' => 'integer',
            'minimum_votes' => 'integer',
            'stake_amount' => 'integer',
            'sample_pack_ready_at' => 'datetime',
            'sample_pack_bypassed_at' => 'datetime',
            'sample_pack_metadata' => 'array',
            'fan_reward_pool_amount' => 'integer',
            'prize_pool_amount' => 'integer',
            'accepted_at' => 'datetime',
            'response_due_at' => 'datetime',
            'ready_due_at' => 'datetime',
            'challenger_ready_at' => 'datetime',
            'opponent_ready_at' => 'datetime',
            'recording_started_at' => 'datetime',
            'recording_ends_at' => 'datetime',
            'voting_started_at' => 'datetime',
            'voting_ends_at' => 'datetime',
            'completed_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DjBattle $battle): void {
            $battle->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_ACCEPTED,
            self::STATUS_RECORDING,
            self::STATUS_VOTING,
            self::STATUS_COMPLETED,
        ]);
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'challenger_dj_profile_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'opponent_dj_profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'winner_dj_profile_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DjBattleEntry::class, 'battle_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(DjBattleVote::class, 'battle_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(DjBattleResult::class, 'battle_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DjBattleEvent::class, 'battle_id');
    }

    public function isParticipantProfile(DjProfile $profile): bool
    {
        return in_array($profile->id, [$this->challenger_dj_profile_id, $this->opponent_dj_profile_id], true);
    }

    public function participantUserIds(): array
    {
        return collect([$this->challenger?->user_id, $this->opponent?->user_id])
            ->filter()
            ->map(fn (int $userId): int => $userId)
            ->values()
            ->all();
    }
}
