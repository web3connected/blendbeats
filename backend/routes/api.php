<?php

use App\Http\Controllers\Api\Auth\AdminAuthController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use App\Http\Controllers\Api\DjLoungeController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('auth')->middleware([AddQueuedCookiesToResponse::class, StartSession::class])->group(function () {
    Route::post('/register', [UserAuthController::class, 'register']);
    Route::post('/login', [UserAuthController::class, 'login']);
    Route::get('/me', [UserAuthController::class, 'me'])->middleware('user.auth');
    Route::post('/logout', [UserAuthController::class, 'logout'])->middleware('user.auth');
});

Route::prefix('dj-lounge')->middleware([AddQueuedCookiesToResponse::class, StartSession::class])->group(function () {
    Route::get('/posts', [DjLoungeController::class, 'index']);
    Route::get('/posts/{post}/comments', [DjLoungeController::class, 'comments']);

    Route::middleware('user.auth')->group(function () {
        Route::post('/posts', [DjLoungeController::class, 'store']);
        Route::post('/posts/{post}/comments', [DjLoungeController::class, 'storeComment']);
        Route::post('/posts/{post}/reaction', [DjLoungeController::class, 'toggleReaction']);
        Route::post('/posts/{post}/repost', [DjLoungeController::class, 'toggleRepost']);
        Route::post('/posts/{post}/bookmark', [DjLoungeController::class, 'toggleBookmark']);
        Route::post('/posts/{post}/report', [DjLoungeController::class, 'report']);
    });
});

Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::get('/me', [AdminAuthController::class, 'me'])->middleware('admin.auth');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->middleware('admin.auth');
});
