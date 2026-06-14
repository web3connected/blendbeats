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

    public function configPathFor(string $field): ?string
    {
        return match ($this->provider.'.'.$field) {
            'paypal.client_id' => 'billing.paypal.client_id',
            'paypal.secret' => 'billing.paypal.secret',
            'paypal.webhook_id' => 'billing.paypal.webhook_id',
            'paypal.merchant_id' => 'billing.paypal.merchant_id',
            'stripe.client_id' => 'cashier.key',
            'stripe.secret' => 'cashier.secret',
            'stripe.webhook_secret' => 'cashier.webhook.secret',
            default => null,
        };
    }

    public function configValueFor(string $field): ?string
    {
        $configPath = $this->configPathFor($field);

        if (! $configPath) {
            return null;
        }

        $value = config($configPath);

        return filled($value) ? (string) $value : null;
    }

    public function effectiveValueFor(string $field): ?string
    {
        $databaseValue = $this->{$field} ?? null;

        if (filled($databaseValue)) {
            return (string) $databaseValue;
        }

        return $this->configValueFor($field);
    }

    public function valueSourceFor(string $field): string
    {
        if (filled($this->{$field} ?? null)) {
            return 'database';
        }

        if (filled($this->configValueFor($field))) {
            return 'env';
        }

        return 'missing';
    }

    public function hasEffectiveValueFor(string $field): bool
    {
        return filled($this->effectiveValueFor($field));
    }

    public function hasEffectiveSecret(): bool
    {
        return $this->hasEffectiveValueFor('secret');
    }

    public function hasEffectiveWebhookSecret(): bool
    {
        return $this->hasEffectiveValueFor('webhook_secret');
    }
}
