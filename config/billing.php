<?php

$membership = require __DIR__.'/membership.php';
$membershipTiers = $membership['tiers'];
$paypalMode = strtolower(trim((string) env('PAYPAL_MODE', 'sandbox')));

if (! in_array($paypalMode, ['sandbox', 'live'], true)) {
    throw new InvalidArgumentException(
        'PAYPAL_MODE must be either "sandbox" or "live".'
    );
}

$paypalIsSandbox = $paypalMode === 'sandbox';

return [
    'stripe' => [
        'mode' => env('STRIPE_MODE', 'test'),
    ],

    'paypal' => [
        'mode' => $paypalMode,
        // Laravel configuration is authoritative for the active PayPal deployment.
        // Environment-specific resources never fall back across Sandbox and Live.
        'client_id' => $paypalIsSandbox ? env('TEST_PAYPAL_CLIENT_ID') : env('PAYPAL_CLIENT_ID'),
        'secret' => $paypalIsSandbox ? env('TEST_PAYPAL_SECRET') : env('PAYPAL_SECRET'),
        'webhook_id' => $paypalIsSandbox ? env('TEST_PAYPAL_WEBHOOK_ID') : env('PAYPAL_WEBHOOK_ID'),
        'required_webhook_headers' => [
            'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-TRANSMISSION-TIME',
            'PAYPAL-TRANSMISSION-SIG',
            'PAYPAL-CERT-URL',
            'PAYPAL-AUTH-ALGO',
        ],
        'enforce_signature' => env(
            'PAYPAL_ENFORCE_SIGNATURE',
            false
        ),

        'enforce_duplicates' => env(
            'PAYPAL_ENFORCE_DUPLICATES',
            false
        ),

        'enforce_replay_protection' => env(
            'PAYPAL_ENFORCE_REPLAY_PROTECTION',
            false
        ),

        'enforce_processing_lock' => env(
            'PAYPAL_ENFORCE_PROCESSING_LOCK',
            false
        ),

        'replay_window_seconds' => env(
            'PAYPAL_REPLAY_WINDOW_SECONDS',
            300
        ),

        'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
        'merchant_id' => env('PAYPAL_MERCHANT_ID'),
        'plans' => [
            'dj_plus' => $paypalIsSandbox ? env('TEST_PAYPAL_PLAN_DJ_PLUS') : env('PAYPAL_PLAN_DJ_PLUS'),
            'dj_pro' => $paypalIsSandbox ? env('TEST_PAYPAL_PLAN_DJ_PRO') : env('PAYPAL_PLAN_DJ_PRO'),
            'dj_elite' => $paypalIsSandbox ? env('TEST_PAYPAL_PLAN_DJ_ELITE') : env('PAYPAL_PLAN_DJ_ELITE'),
        ],
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
        'free_tier' => $membership['default_tier'],
        'tiers' => [
            'free' => [
                'name' => $membershipTiers['free']['name'],
                'stripe_price_id' => null,
                'price_cents' => 0,
                'billing_interval' => 'forever',
                'storage_bytes' => $membershipTiers['free']['services']['storage']['max_storage_bytes'],
                'advertising_groups' => $membershipTiers['free']['services']['advertising']['groups'],
                'services' => $membershipTiers['free']['services'],
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
                'name' => $membershipTiers['dj_plus']['name'],
                'stripe_price_id' => env('STRIPE_PRICE_DJ_PLUS'),
                'stripe_lookup_key' => env('STRIPE_LOOKUP_DJ_PLUS', 'blendbeats_dj_plus_monthly_999'),
                'price_cents' => 999,
                'billing_interval' => 'monthly',
                'storage_bytes' => $membershipTiers['dj_plus']['services']['storage']['max_storage_bytes'],
                'advertising_groups' => $membershipTiers['dj_plus']['services']['advertising']['groups'],
                'services' => $membershipTiers['dj_plus']['services'],
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
                'name' => $membershipTiers['dj_pro']['name'],
                'stripe_price_id' => env('STRIPE_PRICE_DJ_PRO'),
                'stripe_lookup_key' => env('STRIPE_LOOKUP_DJ_PRO', 'blendbeats_dj_pro_monthly_1999'),
                'price_cents' => 1999,
                'billing_interval' => 'monthly',
                'storage_bytes' => $membershipTiers['dj_pro']['services']['storage']['max_storage_bytes'],
                'advertising_groups' => $membershipTiers['dj_pro']['services']['advertising']['groups'],
                'services' => $membershipTiers['dj_pro']['services'],
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
                'name' => $membershipTiers['dj_elite']['name'],
                'stripe_price_id' => env('STRIPE_PRICE_DJ_ELITE'),
                'stripe_lookup_key' => env('STRIPE_LOOKUP_DJ_ELITE', 'blendbeats_dj_elite_monthly_3999'),
                'price_cents' => 3999,
                'billing_interval' => 'monthly',
                'storage_bytes' => $membershipTiers['dj_elite']['services']['storage']['max_storage_bytes'],
                'advertising_groups' => $membershipTiers['dj_elite']['services']['advertising']['groups'],
                'services' => $membershipTiers['dj_elite']['services'],
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

    'affiliate' => [
        'membership_credit' => [
            'tier' => 'dj_plus',
            'days' => 30,
            'expires_after_months' => 12,
            'expiring_notice_days' => 7,
        ],
    ],
];
