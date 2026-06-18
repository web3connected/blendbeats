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

    'scratch_video_monthly_limits' => [
        'free' => 3,
        'dj_plus' => 50,
        'dj_pro' => 150,
        'dj_elite' => null,
    ],
];
