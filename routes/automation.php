<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutomationProxyController;

Route::any('/automation/{path?}', AutomationProxyController::class)
    ->where('path', '.*')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    ])
    ->name('automation.proxy');
