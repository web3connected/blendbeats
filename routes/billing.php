<?php

use App\Http\Controllers\Api\AccountGamificationController;
use App\Http\Controllers\Api\AffiliateAccountController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\GamificationBadgeController;
use Illuminate\Support\Facades\Route;

Route::get('billing/plans', [BillingController::class, 'plans'])
    ->name('api.billing.plans');
Route::get('billing/paypal/subscription-config', [BillingController::class, 'paypalSubscriptionConfig'])
    ->name('api.billing.paypal.subscription-config');
Route::get('/account/subscription', [BillingController::class, 'subscriptionDetails'])
    ->middleware('public.auth')
    ->name('api.account.subscription');
Route::get('/account/gamification', [AccountGamificationController::class, 'show'])
    ->middleware('public.auth')
    ->name('api.account.gamification');
Route::get('/account/gamification/events', [AccountGamificationController::class, 'events'])
    ->middleware('public.auth')
    ->name('api.account.gamification.events');
Route::get('/account/affiliate', [AffiliateAccountController::class, 'show'])
    ->middleware('public.auth')
    ->name('api.account.affiliate.show');
Route::post('/account/affiliate', [AffiliateAccountController::class, 'store'])
    ->middleware('public.auth')
    ->name('api.account.affiliate.store');
Route::get('/account/affiliate/summary', [AffiliateAccountController::class, 'summary'])
    ->middleware('public.auth')
    ->name('api.account.affiliate.summary');
Route::get('/account/affiliate/referrals', [AffiliateAccountController::class, 'referrals'])
    ->middleware('public.auth')
    ->name('api.account.affiliate.referrals');
Route::get('/account/affiliate/rewards', [AffiliateAccountController::class, 'rewards'])
    ->middleware('public.auth')
    ->name('api.account.affiliate.rewards');
Route::post('/account/affiliate/rewards/{reward}/redeem', [AffiliateAccountController::class, 'redeemReward'])
    ->middleware('public.auth')
    ->name('api.account.affiliate.rewards.redeem');
Route::get('/account/affiliate/payouts', [AffiliateAccountController::class, 'payouts'])
    ->middleware('public.auth')
    ->name('api.account.affiliate.payouts');
Route::post('/account/affiliate/payouts', [AffiliateAccountController::class, 'requestPayout'])
    ->middleware('public.auth')
    ->name('api.account.affiliate.payouts.store');
Route::get('/gamification/badges', [GamificationBadgeController::class, 'index'])
    ->name('api.gamification.badges.index');
Route::prefix('billing')
    ->middleware('public.auth')
    ->name('api.billing.')
    ->group(function (): void {
        Route::get('subscription', [BillingController::class, 'subscription'])->name('subscription');
        Route::get('payment-methods', [BillingController::class, 'paymentMethods'])->name('payment-methods');
        Route::post('checkout', [BillingController::class, 'checkout'])->name('checkout');
        Route::post('paypal/subscription-approved', [BillingController::class, 'paypalSubscriptionApproved'])
            ->name('paypal.subscription-approved');
        Route::post('portal', [BillingController::class, 'portal'])->name('portal');
    });
