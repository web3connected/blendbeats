<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleManagerController;
use App\Http\Controllers\Admin\UserAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showLogin'])->name('login');

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.store');
        Route::get('/password/forgot', [AuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('/password/email', [AuthController::class, 'sendPasswordResetLink'])->name('password.email');
        Route::get('/password/reset/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.update');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('admin.auth')->name('logout');

        Route::get('/', DashboardController::class)->middleware('admin.auth')->name('dashboard');
        Route::get('/account', AccountController::class)->middleware('admin.auth')->name('account');
        Route::post('/account/profile', [AccountController::class, 'updateProfile'])->middleware('admin.auth')->name('account.profile');
        Route::post('/account/password', [AccountController::class, 'updatePassword'])->middleware('admin.auth')->name('account.password');
        Route::post('/account/avatar', [AccountController::class, 'updateAvatar'])->middleware('admin.auth')->name('account.avatar');
        Route::resource('/admin-center/admin-users', AdminUserController::class)
            ->middleware('admin.auth')
            ->except(['show'])
            ->parameters(['admin-users' => 'adminUser']);
        Route::resource('/admin-center/role-manager', RoleManagerController::class)
            ->middleware(['admin.auth', 'role:sys-admin|admin,admin'])
            ->except(['show'])
            ->parameters(['role-manager' => 'roleManager']);
        Route::resource('/admin-center/user-accounts', UserAccountController::class)
            ->middleware('admin.auth')
            ->except(['show'])
            ->parameters(['user-accounts' => 'userAccount']);
    });
