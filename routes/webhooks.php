<?php

use App\Http\Controllers\Api\PayPalWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('paypal/webhook', [PayPalWebhookController::class, 'handle'])
    ->name('api.paypal.webhook');
