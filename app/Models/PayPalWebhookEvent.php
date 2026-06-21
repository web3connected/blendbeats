<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayPalWebhookEvent extends Model
{
    protected $table = 'paypal_webhook_events';

    protected $fillable = [
        'event_type',
        'resource_id',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
