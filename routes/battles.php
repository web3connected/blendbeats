<?php

use App\Http\Controllers\Api\DjBattleController;
use Illuminate\Support\Facades\Route;

Route::prefix('battles')
    ->name('api.battles.')
    ->group(function (): void {
        Route::get('/', [DjBattleController::class, 'index'])->name('index');
        Route::get('{battle}', [DjBattleController::class, 'show'])->name('show');

        Route::middleware('public.auth')->group(function (): void {
            Route::post('/', [DjBattleController::class, 'store'])->name('store');
            Route::post('{battle}/accept', [DjBattleController::class, 'accept'])->name('accept');
            Route::post('{battle}/decline', [DjBattleController::class, 'decline'])->name('decline');
            Route::post('{battle}/cancel', [DjBattleController::class, 'cancel'])->name('cancel');
            Route::post('{battle}/extend', [DjBattleController::class, 'extend'])->name('extend');
            Route::post('{battle}/ready', [DjBattleController::class, 'ready'])->name('ready');
            Route::post('{battle}/ready/test-opponent', [DjBattleController::class, 'readyOtherParticipantForTesting'])->name('ready.test-opponent');
            Route::post('{battle}/sample-pack/bypass', [DjBattleController::class, 'bypassSamplePack'])->name('sample-pack.bypass');
            Route::post('{battle}/entries', [DjBattleController::class, 'submitEntry'])->name('entries.store');
            Route::post('{battle}/entries/test-duplicate', [DjBattleController::class, 'duplicateEntryForTesting'])->name('entries.test-duplicate');
        });
    });

Route::get('account/battles', [DjBattleController::class, 'account'])
    ->middleware('public.auth')
    ->name('api.account.battles.index');
