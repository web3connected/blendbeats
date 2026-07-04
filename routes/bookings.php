<?php

use App\Http\Controllers\Api\AccountDjBookingController;
use App\Http\Controllers\Api\AdminDjBookingController;
use App\Http\Controllers\Api\DjBookingRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('dj-hub')
    ->name('api.dj-hub.')
    ->group(function (): void {
        Route::get('djs/{handle}/booking-settings', [DjBookingRequestController::class, 'settings'])
            ->name('booking-settings.show');
        Route::post('djs/{handle}/booking-requests', [DjBookingRequestController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('booking-requests.store');
    });

Route::prefix('account/bookings')
    ->middleware('public.auth')
    ->name('api.account.bookings.')
    ->group(function (): void {
        Route::get('/', [AccountDjBookingController::class, 'index'])->name('index');
        Route::get('{booking}', [AccountDjBookingController::class, 'show'])->name('show');
        Route::post('{booking}/accept', [AccountDjBookingController::class, 'accept'])->name('accept');
        Route::post('{booking}/decline', [AccountDjBookingController::class, 'decline'])->name('decline');
        Route::post('{booking}/needs-discussion', [AccountDjBookingController::class, 'needsDiscussion'])->name('needs-discussion');
        Route::post('{booking}/cancel', [AccountDjBookingController::class, 'cancel'])->name('cancel');
        Route::post('{booking}/complete', [AccountDjBookingController::class, 'complete'])->name('complete');
        Route::post('{booking}/mark-paid', [AccountDjBookingController::class, 'markPaid'])->name('mark-paid');
    });

Route::prefix('admin/bookings')
    ->middleware('admin.auth')
    ->name('api.admin.bookings.')
    ->group(function (): void {
        Route::get('/', [AdminDjBookingController::class, 'index'])->name('index');
        Route::get('{booking}', [AdminDjBookingController::class, 'show'])->name('show');
    });
