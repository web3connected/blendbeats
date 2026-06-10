<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ResourcePlaceholderController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:admin')->group(function (): void {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('admin.auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

    Route::prefix('resources')->name('resources.')->group(function (): void {
        Route::get('{resource}', ResourcePlaceholderController::class)
            ->whereIn('resource', ['users', 'roles', 'permissions', 'settings', 'content', 'reports'])
            ->name('show');
    });
});
