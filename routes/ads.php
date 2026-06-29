<?php

use App\Http\Controllers\Api\AdvertisementDisplayController;
use App\Http\Controllers\Api\AdvertisementEventController;
use Illuminate\Support\Facades\Route;

Route::get('ads/display', [AdvertisementDisplayController::class, 'show'])->name('api.ads.display');
Route::post('ads/events', [AdvertisementEventController::class, 'store'])
    ->name('api.ads.events.store');
