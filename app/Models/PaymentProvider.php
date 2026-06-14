<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProvider extends Model
{
    protected $fillable = [
        'provider',
        'display_name',
        'mode',
        'is_active',
        'is_primary',
        'client_id',
        'secret',
        'webhook_id',
        'webhook_secret',
        'merchant_id',
        'dashboard_url',
        'notes',
        'supported_features',
    ];

    protected $hidden = [
        'secret',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
            'secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'supported_features' => 'array',
        ];
    }

    public function hasSecret(): bool
    {
        return filled($this->secret);
    }

    public function hasWebhookSecret(): bool
    {
        return filled($this->webhook_secret);
    }
}
