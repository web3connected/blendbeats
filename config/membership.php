<?php

$megabyte = 1024 * 1024;
$gigabyte = 1024 * $megabyte;

return [
    'default_tier' => 'free',

    'tiers' => [
        'free' => [
            'name' => 'Free',
            'services' => [
                'community' => [
                    'site_participation' => true,
                    'battle_participation' => true,
                    'battle_voting' => true,
                ],
                'storage' => [
                    'enabled' => true,
                    'max_storage_bytes' => 1 * $gigabyte,
                ],
                'live_streaming' => [
                    'enabled' => true,
                    'max_duration_minutes' => 15,
                    'weekly_limit' => 1,
                    'monthly_limit' => 4,
                    'recording_enabled' => false,
                ],
                'battles' => [
                    'enabled' => true,
                    'max_wager_coins' => 2500,
                    'voting_enabled' => true,
                ],
                'advertising' => [
                    'level' => 'minimal',
                    'groups' => ['F'],
                ],
                'bookings' => [
                    'enabled' => false,
                ],
            ],
        ],

        'dj_plus' => [
            'name' => 'DJ Plus',
            'services' => [
                'community' => [
                    'site_participation' => true,
                    'battle_participation' => true,
                    'battle_voting' => true,
                ],
                'storage' => [
                    'enabled' => true,
                    'max_storage_bytes' => 5 * $gigabyte,
                ],
                'live_streaming' => [
                    'enabled' => true,
                    'max_duration_minutes' => 30,
                    'weekly_limit' => 3,
                    'monthly_limit' => 15,
                    'recording_enabled' => false,
                ],
                'battles' => [
                    'enabled' => true,
                    'max_wager_coins' => 10000,
                    'voting_enabled' => true,
                ],
                'advertising' => [
                    'level' => 'basic',
                    'groups' => ['E', 'F'],
                ],
                'bookings' => [
                    'enabled' => false,
                ],
            ],
        ],

        'dj_pro' => [
            'name' => 'DJ Pro',
            'services' => [
                'community' => [
                    'site_participation' => true,
                    'battle_participation' => true,
                    'battle_voting' => true,
                ],
                'storage' => [
                    'enabled' => true,
                    'max_storage_bytes' => 15 * $gigabyte,
                ],
                'live_streaming' => [
                    'enabled' => true,
                    'max_duration_minutes' => 60,
                    'weekly_limit' => 6,
                    'monthly_limit' => 30,
                    'recording_enabled' => true,
                ],
                'battles' => [
                    'enabled' => true,
                    'max_wager_coins' => 25000,
                    'voting_enabled' => true,
                ],
                'advertising' => [
                    'level' => 'standard',
                    'groups' => ['C', 'D', 'E', 'F'],
                ],
                'bookings' => [
                    'enabled' => true,
                ],
            ],
        ],

        'dj_elite' => [
            'name' => 'DJ Elite',
            'services' => [
                'community' => [
                    'site_participation' => true,
                    'battle_participation' => true,
                    'battle_voting' => true,
                ],
                'storage' => [
                    'enabled' => true,
                    'max_storage_bytes' => 30 * $gigabyte,
                ],
                'live_streaming' => [
                    'enabled' => true,
                    'max_duration_minutes' => 120,
                    'weekly_limit' => 12,
                    'monthly_limit' => 60,
                    'recording_enabled' => true,
                ],
                'battles' => [
                    'enabled' => true,
                    'max_wager_coins' => 50000,
                    'voting_enabled' => true,
                ],
                'advertising' => [
                    'level' => 'aggressive',
                    'groups' => ['A', 'B', 'C', 'D', 'E', 'F'],
                ],
                'bookings' => [
                    'enabled' => true,
                ],
            ],
        ],
    ],
];
