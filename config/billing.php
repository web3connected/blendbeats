<?php

$megabyte = 1024 * 1024;
$gigabyte = 1024 * $megabyte;

return [
    'stripe' => [
        'mode' => env('STRIPE_MODE', 'test'),
    ],

    'promotion' => [
        'campaign_types' => [
            'one_day' => [
                'name' => '1-Day Campaign',
                'duration_days' => 1,
                'description' => 'Promotes a DJ profile or mix for one day.',
            ],
            'seven_day' => [
                'name' => '7-Day Campaign',
                'duration_days' => 7,
                'description' => 'Promotes a DJ profile or mix for seven consecutive days.',
            ],
        ],
        'advertising_groups' => [
            'A' => [
                'name' => 'Group A',
                'level' => 'Premium',
                'description' => 'Highest visibility across premium site locations with limited inventory.',
            ],
            'B' => [
                'name' => 'Group B',
                'level' => 'High',
                'description' => 'High visibility across multiple site locations.',
            ],
            'C' => [
                'name' => 'Group C',
                'level' => 'Standard',
                'description' => 'Standard visibility in major community sections.',
            ],
            'D' => [
                'name' => 'Group D',
                'level' => 'Community',
                'description' => 'Community visibility for growing DJs.',
            ],
            'E' => [
                'name' => 'Group E',
                'level' => 'Entry',
                'description' => 'Entry-level promotion inventory.',
            ],
            'F' => [
                'name' => 'Group F',
                'level' => 'Basic',
                'description' => 'Basic promotional access available to Free Tier users.',
            ],
        ],
    ],

    'subscription' => [
        'default_type' => 'dj_membership',
        'free_tier' => 'free',
        'tiers' => [
            'free' => [
                'name' => 'Free Tier',
                'stripe_price_id' => null,
                'storage_bytes' => 500 * $megabyte,
                'advertising_groups' => ['F'],
                'purpose' => 'Allows DJs to fully participate in the BlendBeats ecosystem without any required subscription.',
                'features' => [
                    'DJ Profile',
                    'DJ Lounge Access',
                    'DJ Hub Listing',
                    'Public Mix Listings',
                    'Portfolio System',
                    'Community Participation',
                    'Basic Analytics',
                    '500 MB Storage',
                    'Access to Basic Promotion Groups',
                ],
                'future_features' => [],
            ],
            'dj_plus' => [
                'name' => 'DJ Plus',
                'stripe_price_id' => env('STRIPE_PRICE_DJ_PLUS'),
                'storage_bytes' => 3 * $gigabyte,
                'advertising_groups' => ['E', 'F'],
                'purpose' => 'Adds extra growth tools for DJs who are ready to promote more consistently.',
                'features' => [
                    'Everything in Free',
                    'More portfolio storage',
                    'Enhanced analytics',
                    'Access to Groups E-F advertising',
                    'Promotion planning tools',
                ],
                'future_features' => [
                    'Profile optimization suggestions',
                    'Mix promotion suggestions',
                ],
            ],
            'dj_pro' => [
                'name' => 'DJ Pro',
                'stripe_price_id' => env('STRIPE_PRICE_DJ_PRO'),
                'storage_bytes' => 10 * $gigabyte,
                'advertising_groups' => ['C', 'D', 'E', 'F'],
                'purpose' => 'Supports active DJs with stronger discovery, analytics, and booking growth tools.',
                'features' => [
                    'Everything in DJ Plus',
                    'Advanced analytics suite',
                    'Priority promotion tools',
                    'Access to Groups C-F advertising',
                    'Booking request tools',
                ],
                'future_features' => [
                    'AI DJ Assistant',
                    'Follower growth insights',
                    'Promotion performance reporting',
                    'Booking inquiry management',
                ],
            ],
            'dj_elite' => [
                'name' => 'DJ Elite',
                'stripe_price_id' => env('STRIPE_PRICE_DJ_ELITE'),
                'storage_bytes' => 25 * $gigabyte,
                'advertising_groups' => ['A', 'B', 'C', 'D', 'E', 'F'],
                'purpose' => 'Premium growth tier for DJs building a professional DJ brand and booking pipeline.',
                'features' => [
                    'Everything in DJ Pro',
                    'Highest portfolio limits',
                    'Access to Groups A-F advertising',
                    'Priority advertising access',
                    'Professional booking system access',
                    'Business management features',
                ],
                'future_features' => [
                    'AI Booking Assistant',
                    'Contract management',
                    'Customer records',
                    'Automated follow-ups',
                    'Lead nurturing',
                    'Future AI agent workflows',
                ],
            ],
        ],
    ],
];
