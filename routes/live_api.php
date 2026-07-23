<?php

use App\Http\Controllers\Api\LiveController;
use App\Http\Controllers\Api\LiveStudioController;
use App\Http\Controllers\Api\LiveTokenController;
use App\Http\Controllers\Api\LiveViewerController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::prefix('live')
    ->middleware('web')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('api.live.')
    ->group(function (): void {
        Route::get('/', [LiveController::class, 'index'])->name('index');
        Route::get('studio', [LiveStudioController::class, 'show'])
            ->middleware('public.auth')
            ->name('studio.show');
        Route::post('start', [LiveStudioController::class, 'start'])
            ->middleware('public.auth')
            ->name('start');
        Route::post('end', [LiveStudioController::class, 'end'])
            ->middleware('public.auth')
            ->name('end');
        Route::post('token', [LiveTokenController::class, 'store'])->name('token.store');
        Route::post('{liveStream}/viewers', [LiveViewerController::class, 'store'])
            ->whereNumber('liveStream')
            ->name('viewers.store');
        Route::delete('{liveStream}/viewers', [LiveViewerController::class, 'destroy'])
            ->whereNumber('liveStream')
            ->name('viewers.destroy');
        Route::get('{username}', [LiveController::class, 'show'])
            ->where('username', '[A-Za-z0-9_-]+')
            ->name('show');
    });
