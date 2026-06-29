<?php

use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('wallet')
    ->middleware('public.auth')
    ->name('api.wallet.')
    ->group(function (): void {
        Route::get('/', [WalletController::class, 'show'])->name('show');
        Route::get('transactions', [WalletController::class, 'transactions'])->name('transactions');
    });
