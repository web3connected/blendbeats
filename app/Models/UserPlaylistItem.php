<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPlaylistItem extends Model
{
    protected $fillable = [
        'user_id',
        'mix_id',
        'position',
        'added_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'added_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mix(): BelongsTo
    {
        return $this->belongsTo(Mix::class);
    }
}
