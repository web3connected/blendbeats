<?php

use App\Services\FeaturedAdNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('featured-ads:sync-notifications', function () {
    $result = app(FeaturedAdNotificationService::class)->syncEndingNotifications();

    $this->info("Featured ad notifications synced. Ending soon: {$result['ending_soon']}. Expired: {$result['expired']}.");
})->purpose('Send featured ad ending notifications and expire completed campaigns');

Schedule::command('featured-ads:sync-notifications')->everyFifteenMinutes();
