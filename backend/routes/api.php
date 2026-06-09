<?php

use App\Http\Controllers\Api\Auth\AdminAuthController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use App\Http\Controllers\Api\DjProfileController;
use App\Http\Controllers\Api\DjHubController;
use App\Http\Controllers\Api\DjLoungeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MediaManagerController;
use App\Http\Controllers\Api\MediaSetupController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('auth')->middleware([AddQueuedCookiesToResponse::class, StartSession::class])->group(function () {
    Route::post('/register', [UserAuthController::class, 'register']);
    Route::post('/login', [UserAuthController::class, 'login']);
    Route::get('/me', [UserAuthController::class, 'me'])->middleware('user.auth');
    Route::post('/avatar', [UserAuthController::class, 'updateAvatar'])->middleware('user.auth');
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

Route::prefix('dj-hub')->group(function () {
    Route::get('/djs', [DjHubController::class, 'index']);
    Route::get('/djs/{handle}', [DjHubController::class, 'show']);
});

Route::prefix('dj')->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'user.auth'])->group(function () {
    Route::get('/profile', [DjProfileController::class, 'show']);
    Route::post('/profile', [DjProfileController::class, 'store']);
});

Route::prefix('media')->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'user.auth'])->group(function () {
    Route::get('/setup', [MediaSetupController::class, 'show']);
    Route::post('/setup', [MediaSetupController::class, 'store']);
    Route::get('/files', [MediaManagerController::class, 'index']);
    Route::post('/files', [MediaManagerController::class, 'store']);
    Route::get('/tree', [MediaManagerController::class, 'tree']);
    Route::get('/files/{file}/stream', [MediaManagerController::class, 'stream'])->name('media.stream');
    Route::delete('/files/{file}', [MediaManagerController::class, 'destroy']);
});

Route::get('/account/features', [MediaSetupController::class, 'features'])
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'user.auth']);

Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::get('/me', [AdminAuthController::class, 'me'])->middleware('admin.auth');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->middleware('admin.auth');
});
