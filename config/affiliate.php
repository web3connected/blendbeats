<?php

return [
    'reward_plan' => env('AFFILIATE_REWARD_PLAN', 'membership_credit'),
    'qualification_event' => env('AFFILIATE_QUALIFICATION_EVENT', 'subscription_qualified'),

    'membership_credit' => [
        'tier' => env('AFFILIATE_MEMBERSHIP_CREDIT_TIER', 'dj_plus'),
        'duration_days' => (int) env('AFFILIATE_MEMBERSHIP_CREDIT_DAYS', 30),
        'expires_after_months' => (int) env('AFFILIATE_MEMBERSHIP_CREDIT_EXPIRES_AFTER_MONTHS', 12),
    ],

    'notifications' => [
        'expiring_soon_days' => (int) env('AFFILIATE_MEMBERSHIP_CREDIT_EXPIRING_SOON_DAYS', 7),
    ],
];
