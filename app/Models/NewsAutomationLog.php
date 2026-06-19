<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsAutomationLog extends Model
{
    protected $fillable = [
        'workflow_name',
        'rule_id',
        'event_id',
        'status',
        'message',
        'payload',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'rule_id' => 'integer',
            'event_id' => 'integer',
            'payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NewsAutomationRule::class, 'rule_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NewsEvent::class, 'event_id');
    }
}
