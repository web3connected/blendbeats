<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdvertisementEvent extends Model
{
    protected $fillable = [
        'advertisable_type',
        'advertisable_id',
        'event_type',
        'placement',
        'session_id',
        'ip_hash',
        'user_agent_hash',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function advertisable(): MorphTo
    {
        return $this->morphTo();
    }
}
