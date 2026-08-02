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
        // PayPal's active deployment is selected exclusively by billing.php.
        // Database and generic environment fallbacks must not cross environments.
        if ($this->provider === 'paypal') {
            return $this->configValueFor($field);
        }

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
        if ($this->provider === 'paypal') {
            return $this->configValueFor($field);
        }

        $databaseValue = $this->{$field} ?? null;

        if (filled($databaseValue)) {
            return (string) $databaseValue;
        }

        return $this->settingValueFor($field);
    }

    public function valueSourceFor(string $field): string
    {
        if ($this->provider === 'paypal') {
            return filled($this->configValueFor($field)) ? 'configuration' : 'missing';
        }

        if (filled($this->{$field} ?? null)) {
            return 'database';
        }

        if (filled($this->configValueFor($field))) {
            return 'configuration';
        }

        if (filled($this->runtimeEnvironmentValueFor($field))) {
            return 'runtime environment';
        }

        if (filled($this->dotenvFileValueFor($field))) {
            return '.env';
        }

        return 'missing';
    }

    public function hasEffectiveValueFor(string $field): bool
    {
        return filled($this->effectiveValueFor($field));
    }

    public function maskedEffectiveValueFor(string $field): ?string
    {
        $value = $this->effectiveValueFor($field);

        if (blank($value)) {
            return null;
        }

        return Str::substr($value, 0, 6).'***';
    }

    public function hasEffectiveSecret(): bool
    {
        return $this->hasEffectiveValueFor('secret');
    }

    public function hasEffectiveWebhookSecret(): bool
    {
        return $this->hasEffectiveValueFor('webhook_secret');
    }

    /**
     * Return the canonical, configuration-authoritative PayPal deployment values.
     *
     * @return array{mode: string, client_id: ?string, secret: ?string, webhook_id: ?string, plans: array{dj_plus: ?string, dj_pro: ?string, dj_elite: ?string}}
     */
    public static function paypalConfiguration(): array
    {
        $mode = config('billing.paypal.mode');

        if (! is_string($mode) || ! in_array($mode, ['sandbox', 'live'], true)) {
            throw new \RuntimeException(
                'PayPal mode configuration must be either sandbox or live.'
            );
        }

        return [
            'mode' => $mode,
            'client_id' => static::filledConfigString('billing.paypal.client_id'),
            'secret' => static::filledConfigString('billing.paypal.secret'),
            'webhook_id' => static::filledConfigString('billing.paypal.webhook_id'),
            'plans' => [
                'dj_plus' => static::filledConfigString('billing.paypal.plans.dj_plus'),
                'dj_pro' => static::filledConfigString('billing.paypal.plans.dj_pro'),
                'dj_elite' => static::filledConfigString('billing.paypal.plans.dj_elite'),
            ],
        ];
    }

    /**
     * Report readiness without returning credentials or PayPal resource IDs.
     *
     * @return array<string, mixed>
     */
    public static function paypalReadiness(): array
    {
        $mode = config('billing.paypal.mode');
        $modeIsValid = is_string($mode) && in_array($mode, ['sandbox', 'live'], true);
        $configuration = [
            'mode' => $modeIsValid ? $mode : null,
            'client_id' => static::filledConfigString('billing.paypal.client_id'),
            'secret' => static::filledConfigString('billing.paypal.secret'),
            'webhook_id' => static::filledConfigString('billing.paypal.webhook_id'),
            'dj_plus_plan_id' => static::filledConfigString('billing.paypal.plans.dj_plus'),
            'dj_pro_plan_id' => static::filledConfigString('billing.paypal.plans.dj_pro'),
            'dj_elite_plan_id' => static::filledConfigString('billing.paypal.plans.dj_elite'),
        ];

        $missingFor = static function (array $fields) use ($configuration): array {
            return array_values(array_filter(
                $fields,
                fn (string $field): bool => blank($configuration[$field] ?? null),
            ));
        };

        $browserMissing = $missingFor([
            'mode',
            'client_id',
            'dj_plus_plan_id',
            'dj_pro_plan_id',
            'dj_elite_plan_id',
        ]);
        $receiptMissing = $missingFor(['mode', 'webhook_id']);
        $signatureMissing = $missingFor(['mode', 'client_id', 'secret', 'webhook_id']);
        $missing = $missingFor([
            'mode',
            'client_id',
            'secret',
            'dj_plus_plan_id',
            'dj_pro_plan_id',
            'dj_elite_plan_id',
            'webhook_id',
        ]);

        return [
            'ready' => $missing === [],
            'mode' => $configuration['mode'],
            'missing' => $missing,
            'browser_subscription' => [
                'ready' => $browserMissing === [],
                'missing' => $browserMissing,
            ],
            'webhook_receipt' => [
                'ready' => $receiptMissing === [],
                'missing' => $receiptMissing,
            ],
            'signature_enforcement' => [
                'ready' => $signatureMissing === [],
                'missing' => $signatureMissing,
            ],
        ];
    }

    private static function filledConfigString(string $path): ?string
    {
        $value = config($path);

        return filled($value) ? (string) $value : null;
    }
}
