<?php

use App\Http\Controllers\Api\Auth\UserAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->name('api.auth.')
    ->group(function (): void {
        Route::post('register', [UserAuthController::class, 'register'])->name('register');
        Route::post('login', [UserAuthController::class, 'login'])->name('login');
        Route::get('me', [UserAuthController::class, 'me'])->name('me');
        Route::patch('account', [UserAuthController::class, 'updateAccount'])->middleware('public.auth')->name('account.update');
        Route::post('logout', [UserAuthController::class, 'logout'])->name('logout');
        Route::post('forgot-password', [UserAuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [UserAuthController::class, 'resetPassword'])->name('reset-password');
        Route::post('avatar', [UserAuthController::class, 'updateAvatar'])->middleware('public.auth')->name('avatar');
    });
