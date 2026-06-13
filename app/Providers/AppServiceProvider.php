<?php

namespace App\Providers;

use App\Listeners\SyncSubscriptionTierFromStripe;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Cashier\Events\WebhookHandled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(WebhookHandled::class, SyncSubscriptionTierFromStripe::class);

        Gate::before(function ($user, string $ability): ?bool {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super-admin', 'sys-admin'])) {
                return true;
            }

            if (isset($user->role) && in_array($user->role, ['super-admin', 'sys-admin'], true)) {
                return true;
            }

            return null;
        });
    }
}
