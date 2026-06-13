<?php

use App\Http\Controllers\Api\MixController;
use App\Http\Controllers\Api\DjHubController;
use App\Http\Controllers\Api\DjLoungeController;
use App\Http\Controllers\Api\DjProfileController;
use App\Http\Controllers\Api\MediaManagerController;
use App\Http\Controllers\Api\MediaSetupController;
use App\Http\Controllers\Api\RatingController;
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
        Route::post('avatar', [UserAuthController::class, 'updateAvatar'])->middleware('public.auth')->name('avatar');
    });

Route::prefix('dj')
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])
    ->name('api.dj.')
    ->group(function (): void {
        Route::get('profile', [DjProfileController::class, 'show'])->name('profile.show');
        Route::post('profile', [DjProfileController::class, 'store'])->name('profile.store');
    });

Route::prefix('media')
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])
    ->name('api.media.')
    ->group(function (): void {
        Route::get('setup', [MediaSetupController::class, 'show'])->name('setup.show');
        Route::post('setup', [MediaSetupController::class, 'store'])->name('setup.store');
        Route::get('files', [MediaManagerController::class, 'index'])->name('files.index');
        Route::post('files', [MediaManagerController::class, 'store'])->name('files.store');
        Route::get('tree', [MediaManagerController::class, 'tree'])->name('tree');
        Route::get('files/{file}/stream', [MediaManagerController::class, 'stream'])->name('files.stream');
        Route::delete('files/{file}', [MediaManagerController::class, 'destroy'])->name('files.destroy');
    });

Route::get('dj-hub/djs', [DjHubController::class, 'index'])->name('api.dj-hub.index');
Route::get('dj-hub/djs/{handle}', [DjHubController::class, 'show'])->name('api.dj-hub.show');

Route::prefix('dj-lounge')->name('api.dj-lounge.')->group(function (): void {
    Route::get('posts', [DjLoungeController::class, 'index'])->name('posts.index');

    Route::middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])->group(function (): void {
        Route::post('posts', [DjLoungeController::class, 'store'])->name('posts.store');
        Route::put('posts/{post}', [DjLoungeController::class, 'update'])->name('posts.update');
        Route::delete('posts/{post}', [DjLoungeController::class, 'destroy'])->name('posts.destroy');
        Route::post('posts/{post}/report', [DjLoungeController::class, 'report'])->name('posts.report');
        Route::post('posts/{post}/replies', [DjLoungeController::class, 'storeReply'])->name('posts.replies.store');
        Route::post('posts/{post}/reaction', [DjLoungeController::class, 'toggleReaction'])->name('posts.reaction');
        Route::post('posts/{post}/repost', [DjLoungeController::class, 'toggleRepost'])->name('posts.repost');
        Route::post('posts/{post}/bookmark', [DjLoungeController::class, 'toggleBookmark'])->name('posts.bookmark');
    });
});

Route::get('mixes', [MixController::class, 'index'])->name('api.mixes.index');
Route::post('mixes/{mix:slug}/play', [MixController::class, 'play'])->name('api.mixes.play');

Route::get('ratings/{type}/{id}', [RatingController::class, 'show'])->name('api.ratings.show');
Route::middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])
    ->group(function (): void {
        Route::post('ratings/{type}/{id}', [RatingController::class, 'store'])->name('api.ratings.store');
        Route::delete('ratings/{type}/{id}', [RatingController::class, 'destroy'])->name('api.ratings.destroy');
    });
