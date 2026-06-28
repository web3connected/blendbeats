<?php

use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('account/wallet')
    ->name('account.wallet.')
    ->group(function () {
        Route::get('/', [WalletController::class, 'index'])->name('index');

        Route::get('/transactions', [WalletController::class, 'transactions'])->name('transactions');

        Route::get('/deposit', [WalletController::class, 'deposit'])->name('deposit');

        Route::get('/withdraw', [WalletController::class, 'withdraw'])->name('withdraw');

        Route::get('/rewards', [WalletController::class, 'rewards'])->name('rewards');

        Route::get('/purchases', [WalletController::class, 'purchases'])->name('purchases');
    });
