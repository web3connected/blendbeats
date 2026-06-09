<?php

return [
    'secret' => env('STRIPE_SECRET_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => env('STRIPE_CURRENCY', 'usd'),
    'api_version' => env('STRIPE_API_VERSION', '2024-08-01'),
];
