<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
            'paypal.webhook_secret' => 'billing.paypal.webhook_secret',
            'paypal.merchant_id' => 'billing.paypal.merchant_id',
            'stripe.client_id' => 'cashier.key',
            'stripe.secret' => 'cashier.secret',
            'stripe.webhook_secret' => 'cashier.webhook.secret',
            default => null,
        };
    }

    public function envKeyFor(string $field): ?string
    {
        return match ($this->provider.'.'.$field) {
            'paypal.client_id' => 'PAYPAL_CLIENT_ID',
            'paypal.secret' => 'PAYPAL_SECRET',
            'paypal.webhook_id' => 'PAYPAL_WEBHOOK_ID',
            'paypal.webhook_secret' => 'PAYPAL_WEBHOOK_SECRET',
            'paypal.merchant_id' => 'PAYPAL_MERCHANT_ID',
            'stripe.client_id' => 'STRIPE_KEY',
            'stripe.secret' => 'STRIPE_SECRET',
            'stripe.webhook_secret' => 'STRIPE_WEBHOOK_SECRET',
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

    public function settingValueFor(string $field): ?string
    {
        return $this->configValueFor($field)
            ?? $this->runtimeEnvironmentValueFor($field)
            ?? $this->dotenvFileValueFor($field);
    }

    private function runtimeEnvironmentValueFor(string $field): ?string
    {
        $key = $this->envKeyFor($field);

        if (! $key) {
            return null;
        }

        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key) ?: null] as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function dotenvFileValueFor(string $field): ?string
    {
        $key = $this->envKeyFor($field);
        $path = base_path('.env');

        if (! $key || ! is_readable($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_starts_with($line, $key.'=')) {
                continue;
            }

            $value = Str::after($line, '=');
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            return filled($value) ? $value : null;
        }

        return null;
    }

    public function effectiveValueFor(string $field): ?string
    {
        $databaseValue = $this->{$field} ?? null;

        if (filled($databaseValue)) {
            return (string) $databaseValue;
        }

        return $this->settingValueFor($field);
    }

    public function valueSourceFor(string $field): string
    {
        if (filled($this->{$field} ?? null)) {
            return 'database';
        }

        if (filled($this->settingValueFor($field))) {
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
