<?php

use App\Http\Controllers\Api\NewsViewController;
use Illuminate\Support\Facades\Route;

Route::prefix('news')->name('news.')->group(function () {
    Route::get('/', [NewsViewController::class, 'index'])->name('index');

    Route::get('/categories/{category:slug}', [NewsViewController::class, 'category'])
        ->name('categories.show');

    Route::get('/{post:slug}', [NewsViewController::class, 'show'])
        ->name('show');
});
