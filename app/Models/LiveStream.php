<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStream extends Model
{
    use HasFactory;

    public const STATUS_LIVE = 'live';
    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'live_channel_id',
        'user_id',
        'agora_channel_name',
        'title',
        'status',
        'max_duration_minutes',
        'started_at',
        'ended_at',
        'recording_enabled',
        'recording_status',
        'recording_started_at',
        'recording_ended_at',
        'recording_storage_path',
    ];

    protected function casts(): array
    {
        return [
            'max_duration_minutes' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'recording_enabled' => 'boolean',
            'recording_started_at' => 'datetime',
            'recording_ended_at' => 'datetime',
        ];
    }

    public function liveChannel(): BelongsTo
    {
        return $this->belongsTo(LiveChannel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
