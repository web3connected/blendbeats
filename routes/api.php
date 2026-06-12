<?php

use App\Http\Controllers\Api\MixController;
use App\Http\Controllers\Api\DjHubController;
use App\Http\Controllers\Api\DjLoungeController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;

Route::prefix('auth')
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class])
    ->name('api.auth.')
    ->group(function (): void {
        Route::post('register', [UserAuthController::class, 'register'])->name('register');
        Route::post('login', [UserAuthController::class, 'login'])->name('login');
        Route::get('me', [UserAuthController::class, 'me'])->name('me');
        Route::post('logout', [UserAuthController::class, 'logout'])->name('logout');
        Route::post('forgot-password', [UserAuthController::class, 'forgotPassword'])->name('forgot-password');
    });

Route::get('dj-hub/djs', [DjHubController::class, 'index'])->name('api.dj-hub.index');
Route::get('dj-hub/djs/{handle}', [DjHubController::class, 'show'])->name('api.dj-hub.show');

Route::prefix('dj-lounge')->name('api.dj-lounge.')->group(function (): void {
    Route::get('posts', [DjLoungeController::class, 'index'])->name('posts.index');
    Route::post('posts', [DjLoungeController::class, 'store'])->name('posts.store');
    Route::post('posts/{post}/reaction', [DjLoungeController::class, 'toggleReaction'])->name('posts.reaction');
    Route::post('posts/{post}/repost', [DjLoungeController::class, 'toggleRepost'])->name('posts.repost');
    Route::post('posts/{post}/bookmark', [DjLoungeController::class, 'toggleBookmark'])->name('posts.bookmark');
});

Route::get('mixes', [MixController::class, 'index'])->name('api.mixes.index');
Route::post('mixes/{mix:slug}/play', [MixController::class, 'play'])->name('api.mixes.play');
