<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Website Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/{path}', function () {
    return view('welcome');
})->where('path', '^(?!(?:admin|api)(?:/|$)).*');
