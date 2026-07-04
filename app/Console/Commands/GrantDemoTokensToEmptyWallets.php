<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class GrantDemoTokensToEmptyWallets extends Command
{
    protected $signature = 'wallet:grant-demo-tokens
        {--amount=1000 : Number of demo tokens to grant}
        {--dry-run : Review eligible users without granting tokens}
        {--force : Allow this command to run in production}';

    protected $description = 'Grant demo beta tokens to users who have no wallet balance.';

    public function handle(WalletService $wallets): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('This command cannot run in production without --force.');

            return self::FAILURE;
        }

        $amount = (int) $this->option('amount');

        if ($amount <= 0) {
            $this->error('The --amount option must be greater than zero.');

            return self::FAILURE;
        }

        $eligibleQuery = $this->eligibleUsersQuery();
        $eligibleCount = (clone $eligibleQuery)->count();

        $this->line('Total users: '.User::query()->count());
        $this->line('Wallets: '.Wallet::query()->count());
        $this->line('Users without wallet: '.User::query()->whereDoesntHave('wallet')->count());
        $this->line('Zero-balance wallets: '.$this->zeroBalanceWalletCount());
        $this->line("Eligible users: {$eligibleCount}");

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No tokens were granted.');

            return self::SUCCESS;
        }

        $granted = 0;
        $skipped = 0;

        $eligibleQuery
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($wallets, $amount, &$granted, &$skipped): void {
                foreach ($users as $user) {
                    $wallet = $wallets->walletFor($user);
                    $totalBalance = (int) $wallet->available_balance + (int) $wallet->locked_balance;

                    if (! $wallet->isActive()) {
                        $skipped++;
                        $this->warn("Skipped {$user->email}: wallet is not active.");

                        continue;
                    }

                    if ($totalBalance !== 0) {
                        $skipped++;

                        continue;
                    }

                    $wallets->credit($user, $amount, WalletService::TYPE_BETA_GRANT, [
                        'idempotency_key' => "demo-mode-empty-wallet-grant:{$user->id}",
                        'description' => 'Demo mode starter token grant.',
                        'metadata' => [
                            'source' => 'wallet:grant-demo-tokens',
                            'demo_mode' => true,
                            'grant_reason' => 'empty_wallet_demo_access',
                        ],
                    ]);

                    $granted++;
                    $this->line("Granted {$amount} tokens to {$user->email}.");
                }
            });

        $this->info('Demo token grant complete.');
        $this->line("Granted users: {$granted}");
        $this->line("Skipped users: {$skipped}");
        $this->line('Total available tokens after grant: '.Wallet::query()->sum('available_balance'));

        return self::SUCCESS;
    }

    private function eligibleUsersQuery(): Builder
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('wallet')
                    ->orWhereHas('wallet', function (Builder $walletQuery): void {
                        $walletQuery
                            ->where('available_balance', 0)
                            ->where('locked_balance', 0);
                    });
            });
    }

    private function zeroBalanceWalletCount(): int
    {
        return Wallet::query()
            ->where('available_balance', 0)
            ->where('locked_balance', 0)
            ->count();
    }
}
