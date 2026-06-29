<?php

use App\Http\Controllers\Api\CounterController;
use Illuminate\Support\Facades\Route;

Route::post('counters/{type}/{id}/{action?}', [CounterController::class, 'increment'])->name('api.counters.increment');
