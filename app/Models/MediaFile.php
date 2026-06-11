<?php

namespace App\Models;

use App\Services\MediaManagerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'admin_id',
        'media_account_id',
        'name',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'collection',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function mediaAccount(): BelongsTo
    {
        return $this->belongsTo(MediaAccount::class);
    }

    public function getFormattedSizeAttribute(): string
    {
        return MediaManagerService::formatBytes($this->size);
    }

    public function getUrlAttribute(): string
    {
        return app(MediaManagerService::class)->getFileUrl($this);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with((string) $this->mime_type, 'audio/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
