<?php

use App\Http\Controllers\Api\CommerceController;
use Illuminate\Support\Facades\Route;

Route::prefix('commerce')
    ->name('api.commerce.')
    ->group(function (): void {
        Route::get('products', [CommerceController::class, 'products'])->name('products');
        Route::get('cart', [CommerceController::class, 'cart'])->name('cart');
        Route::post('cart/items', [CommerceController::class, 'addToCart'])->name('cart.items.store');
        Route::patch('cart/items/{item}', [CommerceController::class, 'updateCartItem'])->name('cart.items.update');
        Route::delete('cart/items/{item}', [CommerceController::class, 'removeCartItem'])->name('cart.items.destroy');
        Route::get('checkout/summary', [CommerceController::class, 'checkoutSummary'])->name('checkout.summary');
    });
