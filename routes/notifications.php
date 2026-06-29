<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')
    ->middleware('public.auth')
    ->name('api.notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::patch('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::patch('{notificationId}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::delete('{notificationId}', [NotificationController::class, 'destroy'])->name('destroy');
    });
