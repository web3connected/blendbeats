<?php

use App\Http\Controllers\Api\RatingController;
use Illuminate\Support\Facades\Route;

Route::get('ratings/{type}/{id}', [RatingController::class, 'show'])
    ->name('api.ratings.show');

Route::middleware('public.auth')
    ->group(function (): void {
        Route::post('ratings/{type}/{id}', [RatingController::class, 'store'])->name('api.ratings.store');
        Route::delete('ratings/{type}/{id}', [RatingController::class, 'destroy'])->name('api.ratings.destroy');
    });
