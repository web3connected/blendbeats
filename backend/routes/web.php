<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showLogin'])->name('login');

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.store');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:admin')->name('logout');

        Route::get('/', DashboardController::class)->middleware('auth:admin')->name('dashboard');
    });
