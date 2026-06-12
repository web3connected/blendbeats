<?php

use App\Http\Controllers\Api\MixController;
use App\Http\Controllers\Api\DjHubController;
use App\Http\Controllers\Api\DjLoungeController;
use Illuminate\Support\Facades\Route;

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
