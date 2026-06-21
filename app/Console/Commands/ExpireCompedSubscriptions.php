<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ExpireCompedSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire-comped';

    protected $description = 'Expire internal complimentary subscriptions that are past their expiration date.';

    public function handle(): int
    {
        $expiredUsers = User::query()
            ->where('billing_provider', 'internal')
            ->whereNotNull('comped_subscription_expires_at')
            ->where('comped_subscription_expires_at', '<=', now())
            ->get();

        foreach ($expiredUsers as $user) {
            $user->forceFill([
                'media_storage_tier' => 'free',
                'paypal_subscription_status' => 'expired',
                'billing_provider' => null,
                'paypal_subscription_approved_at' => null,
                'comped_subscription_expires_at' => null,
                'comped_subscription_reason' => null,
                'comped_by_user_id' => null,
            ])->save();
        }

        $this->info("Expired {$expiredUsers->count()} complimentary subscription(s).");

        return self::SUCCESS;
    }
}
