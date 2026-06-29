<?php

use App\Http\Controllers\Api\DjFollowController;
use App\Http\Controllers\Api\DjHubController;
use App\Http\Controllers\Api\DjLoungeController;
use App\Http\Controllers\Api\DjProfileController;
use App\Http\Controllers\Api\DjScratchController;
use App\Http\Controllers\Api\LoungeLiveStateController;
use Illuminate\Support\Facades\Route;

Route::prefix('dj')
    ->middleware('public.auth')
    ->name('api.dj.')
    ->group(function (): void {
        Route::get('profile', [DjProfileController::class, 'show'])->name('profile.show');
        Route::post('profile', [DjProfileController::class, 'store'])->name('profile.store');
    });

Route::prefix('dj-hub')
    ->name('api.dj-hub.')
    ->group(function (): void {
        Route::get('djs', [DjHubController::class, 'index'])->name('index');
        Route::get('djs/{handle}', [DjHubController::class, 'show'])->name('show');

        Route::middleware('public.auth')->group(function (): void {
            Route::post('djs/{handle}/follow', [DjFollowController::class, 'store'])->name('follow');
            Route::delete('djs/{handle}/follow', [DjFollowController::class, 'destroy'])->name('unfollow');
        });
    });

Route::get('lounge/live-state', [LoungeLiveStateController::class, 'show'])->name('api.lounge.live-state');
Route::get('dj-scratches', [DjScratchController::class, 'index'])->name('api.dj-scratches.index');

Route::prefix('dj-lounge')->name('api.dj-lounge.')->group(function (): void {
    Route::get('posts', [DjLoungeController::class, 'index'])->name('posts.index');

    Route::middleware('public.auth')->group(function (): void {
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
