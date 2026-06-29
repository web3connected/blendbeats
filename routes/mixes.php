<?php

use App\Http\Controllers\Api\MixController;
use App\Http\Controllers\Api\UserPlaylistController;
use Illuminate\Support\Facades\Route;

Route::get('mixes', [MixController::class, 'index'])->name('api.mixes.index');
Route::post('mixes/{mix:slug}/play', [MixController::class, 'play'])->name('api.mixes.play');

Route::prefix('user-playlist')
    ->middleware('public.auth')
    ->name('api.user-playlist.')
    ->group(function (): void {
        Route::get('/', [UserPlaylistController::class, 'index'])->name('index');
        Route::post('mixes/{mix}', [UserPlaylistController::class, 'store'])->name('mixes.store');
        Route::delete('mixes/{mix}', [UserPlaylistController::class, 'destroy'])->name('mixes.destroy');
    });
