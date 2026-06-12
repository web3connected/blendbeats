<?php

return [
    'default_tier' => env('MEDIA_STORAGE_DEFAULT_TIER', 'free'),

    'avatar' => [
        'max_kilobytes' => (int) env('MEDIA_AVATAR_MAX_KILOBYTES', 5120),
    ],

    'tier_aliases' => [
        'starter' => 'free',
        'growth' => 'dj_plus',
        'premium' => 'dj_pro',
    ],
];
