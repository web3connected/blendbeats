<?php

use App\Http\Controllers\Api\LiveController;
use App\Http\Controllers\Api\LiveStudioController;
use App\Http\Controllers\Api\LiveTokenController;
use App\Http\Controllers\Api\LiveViewerController;
use App\Http\Controllers\Api\LiveReplayController;
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
        Route::post('{liveStream}/recording', [LiveStudioController::class, 'recording'])
            ->middleware('public.auth')
            ->whereNumber('liveStream')
            ->name('recording.store');
        Route::post('token', [LiveTokenController::class, 'store'])->name('token.store');
        Route::get('replays/{liveStream}', [LiveReplayController::class, 'show'])->whereNumber('liveStream')->name('replays.show');
        Route::post('replays/{liveStream}/view', [LiveReplayController::class, 'view'])->whereNumber('liveStream')->name('replays.view');
        Route::post('replays/{liveStream}/like', [LiveReplayController::class, 'like'])->middleware('public.auth')->whereNumber('liveStream')->name('replays.like');
        Route::post('replays/{liveStream}/comments', [LiveReplayController::class, 'comment'])->middleware('public.auth')->whereNumber('liveStream')->name('replays.comments.store');
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
