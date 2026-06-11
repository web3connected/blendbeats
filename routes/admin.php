<?php

use App\Http\Controllers\Admin\AdminUserController;
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
    Route::get('account', ResourcePlaceholderController::class)
        ->defaults('resource', 'account')
        ->name('account');
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

    Route::prefix('admin-center')->name('admin-center.')->group(function (): void {
        Route::get('{resource}', ResourcePlaceholderController::class)
            ->whereIn('resource', ['admin-users', 'roles', 'permissions'])
            ->name('show');
    });

    Route::resource('admincenter/adminusers', AdminUserController::class)
        ->parameters(['adminusers' => 'adminuser'])
        ->names('admincenter.adminusers');

    Route::get('user-accounts', ResourcePlaceholderController::class)
        ->defaults('resource', 'users')
        ->name('user-accounts');

    Route::prefix('resources')->name('resources.')->group(function (): void {
        Route::get('{resource}', ResourcePlaceholderController::class)
            ->whereIn('resource', ['users', 'admin-users', 'roles', 'permissions', 'settings', 'content', 'reports'])
            ->name('show');
    });
});
