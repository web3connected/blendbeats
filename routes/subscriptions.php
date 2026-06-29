<?php

use App\Http\Controllers\Admin\AdminAffiliateAnalyticsController;
use App\Http\Controllers\Admin\UserSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin.auth')->group(function (): void {
    Route::post('/admin/users/{user}/grant-free-subscription', [UserSubscriptionController::class, 'grantFreeSubscription']);
    Route::post('/admin/users/{user}/revoke-free-subscription', [UserSubscriptionController::class, 'revokeFreeSubscription']);
    Route::get('/admin/affiliate-analytics', AdminAffiliateAnalyticsController::class)
        ->middleware('permission:affiliates.view,admin')
        ->name('api.admin.affiliate-analytics');
});
