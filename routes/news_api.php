<?php

use App\Http\Controllers\Api\NewsCommentController;
use App\Http\Controllers\Api\NewsController;
use Illuminate\Support\Facades\Route;

Route::prefix('news')
    ->name('api.news.')
    ->group(function (): void {
        Route::get('/', [NewsController::class, 'index'])->name('index');
        Route::get('categories', [NewsController::class, 'categories'])->name('categories');
        Route::get('categories/{slug}', [NewsController::class, 'category'])->name('categories.show');
        Route::get('{post:slug}/comments', [NewsCommentController::class, 'index'])->name('comments.index');
        Route::post('{post:slug}/comments', [NewsCommentController::class, 'store'])->name('comments.store');
        Route::middleware('public.auth')->group(function (): void {
            Route::patch('comments/{comment}', [NewsCommentController::class, 'update'])->name('comments.update');
            Route::delete('comments/{comment}', [NewsCommentController::class, 'destroy'])->name('comments.destroy');
        });
        Route::get('{slug}', [NewsController::class, 'show'])->name('show');
    });
