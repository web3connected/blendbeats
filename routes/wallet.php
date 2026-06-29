<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet Routes
|--------------------------------------------------------------------------
*/

Route::view('/account/wallet', 'welcome')
    ->middleware('auth')
    ->name('account.wallet.index');
