<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Website Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/public.php';
require __DIR__.'/automation.php';
require __DIR__.'/news.php';
require __DIR__.'/wallet.php';

// Internal app endpoints keep their /api URLs but run through the web stack.
Route::prefix('api')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->group(function (): void {
        require __DIR__.'/auth.php';
        require __DIR__.'/media.php';
        require __DIR__.'/djs.php';
        require __DIR__.'/news_api.php';
        require __DIR__.'/feature_ads.php';
        require __DIR__.'/subscriptions.php';
        require __DIR__.'/billing.php';
        require __DIR__.'/notifications.php';
        require __DIR__.'/mixes.php';
        require __DIR__.'/wallet_api.php';
        require __DIR__.'/battles.php';
        require __DIR__.'/commerce.php';
        require __DIR__.'/ads.php';
        require __DIR__.'/counters.php';
        require __DIR__.'/ratings.php';
    });

Route::get('/{path}', function () {
    return view('welcome');
})->where('path', '^(?!(?:admin|api)(?:/|$)).*');
