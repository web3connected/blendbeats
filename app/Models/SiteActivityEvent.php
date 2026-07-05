<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'occurred_at',
    'user_id',
    'admin_id',
    'visitor_key',
    'session_id_hash',
    'ip_hash',
    'method',
    'path',
    'route_name',
    'status_code',
    'duration_ms',
    'referrer_host',
    'referrer_url',
    'user_agent',
    'device_type',
    'is_bot',
    'is_ajax',
])]
class SiteActivityEvent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'is_bot' => 'boolean',
            'is_ajax' => 'boolean',
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
}
