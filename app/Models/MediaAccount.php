<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaAccount extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'account_slug',
        'disk',
        'root_path',
        'storage_tier',
        'storage_limit_bytes',
        'storage_used_bytes',
        'status',
        'activated_at',
        'last_scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'storage_limit_bytes' => 'integer',
            'storage_used_bytes' => 'integer',
            'activated_at' => 'datetime',
            'last_scanned_at' => 'datetime',
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

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }
}
