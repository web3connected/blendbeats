<?php

return [
    'default_tier' => env('MEDIA_STORAGE_DEFAULT_TIER', 'starter'),

    'tiers' => [
        'starter' => [
            'label' => 'Starter',
            'limit_bytes' => 500 * 1024 * 1024,
        ],
        'growth' => [
            'label' => 'Growth',
            'limit_bytes' => 3 * 1024 * 1024 * 1024,
        ],
        'pro' => [
            'label' => 'Pro',
            'limit_bytes' => 5 * 1024 * 1024 * 1024,
        ],
    ],
];
