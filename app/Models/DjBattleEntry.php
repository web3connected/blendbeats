<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjBattleEntry extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_RECORDING = 'recording';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_FORFEITED = 'forfeited';

    protected $fillable = [
        'battle_id',
        'dj_profile_id',
        'user_id',
        'media_file_id',
        'audio_media_file_id',
        'status',
        'title',
        'notes',
        'duration_seconds',
        'metadata',
        'recording_started_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'metadata' => 'array',
            'recording_started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(DjBattle::class, 'battle_id');
    }

    public function djProfile(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function audioMediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'audio_media_file_id');
    }
}
