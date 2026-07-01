<?php

return [
    'beta_token_demo_mode' => env('BETA_TOKEN_DEMO_MODE', true),
    'default_beta_tokens' => (int) env('DEFAULT_BETA_TOKENS', 500),
    'allow_admin_manual_grants' => env('BETA_TOKEN_ADMIN_GRANTS', true),
    'allow_battle_staking_with_test_tokens' => env('BETA_TOKEN_BATTLE_STAKING', true),
    'allow_fan_reward_simulation' => env('BETA_TOKEN_FAN_REWARD_SIMULATION', true),
    'allow_winner_payout_simulation' => env('BETA_TOKEN_WINNER_PAYOUT_SIMULATION', true),
    'withdrawals_enabled' => env('WALLET_WITHDRAWALS_ENABLED', false),
    'withdrawals_disabled_message' => 'Withdrawals are disabled during beta. Test tokens cannot be withdrawn or converted to cash.',
];
