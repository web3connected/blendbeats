<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Website Routes
|--------------------------------------------------------------------------
*/


require __DIR__.'/public.php';
require __DIR__.'/automation.php';
require __DIR__.'/news.php';
require __DIR__.'/wallet.php';

Route::get('/{path}', function () {
    return view('welcome');
})->where('path', '^(?!(?:admin|api)(?:/|$)).*');
