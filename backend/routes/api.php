<?php

use App\Http\Controllers\Api\Auth\AdminAuthController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('auth')->group(function () {
    Route::post('/register', [UserAuthController::class, 'register']);
    Route::post('/login', [UserAuthController::class, 'login']);
    Route::get('/me', [UserAuthController::class, 'me'])->middleware('auth:web');
    Route::post('/logout', [UserAuthController::class, 'logout'])->middleware('auth:web');
});

Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::get('/me', [AdminAuthController::class, 'me'])->middleware('auth:admin');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->middleware('auth:admin');
});
