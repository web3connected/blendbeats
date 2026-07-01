<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Database\Seeder;

class BattleTestingWalletSeeder extends Seeder
{
    public const TRANSACTION_TYPE = WalletService::TYPE_BETA_GRANT;

    public function run(): void
    {
        $minimum = max(1, (int) env('BATTLE_TEST_WALLET_MIN_TOKENS', 1000));
        $maximum = max($minimum, (int) env('BATTLE_TEST_WALLET_MAX_TOKENS', 100000));
        $wallets = app(WalletService::class);

        User::query()
            ->orderBy('id')
            ->each(function (User $user) use ($wallets, $minimum, $maximum): void {
                $wallet = $wallets->walletFor($user);

                if ($wallet->transactions()
                    ->where('type', self::TRANSACTION_TYPE)
                    ->where('description', 'Randomized demo wallet balance for battle testing.')
                    ->exists()) {
                    return;
                }

                $currentTotal = (int) $wallet->available_balance + (int) $wallet->locked_balance;

                if ($currentTotal >= $maximum) {
                    return;
                }

                $targetTotal = random_int(max($minimum, $currentTotal + 1), $maximum);
                $creditAmount = $targetTotal - $currentTotal;

                $wallets->credit($user, $creditAmount, self::TRANSACTION_TYPE, [
                    'description' => 'Randomized demo wallet balance for battle testing.',
                    'metadata' => [
                        'source' => 'battle_testing_wallet_seeder',
                        'minimum_tokens' => $minimum,
                        'maximum_tokens' => $maximum,
                        'target_total_balance' => $targetTotal,
                    ],
                ]);
            });
    }
}
