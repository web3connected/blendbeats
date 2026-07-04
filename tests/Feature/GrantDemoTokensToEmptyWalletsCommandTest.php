<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrantDemoTokensToEmptyWalletsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grants_demo_tokens_only_to_users_with_no_wallet_balance(): void
    {
        $wallets = app(WalletService::class);

        $withoutWallet = User::factory()->create();
        $zeroBalanceWallet = User::factory()->create();
        $fundedWallet = User::factory()->create();

        $wallets->walletFor($zeroBalanceWallet);
        $wallets->credit($fundedWallet, 250, WalletService::TYPE_BETA_GRANT, [
            'description' => 'Existing demo token grant.',
        ]);

        $this->artisan('wallet:grant-demo-tokens', ['--amount' => 1000])
            ->expectsOutput('Eligible users: 2')
            ->expectsOutput('Granted users: 2')
            ->assertExitCode(0);

        $this->assertSame(1000, $withoutWallet->wallet()->firstOrFail()->available_balance);
        $this->assertSame(1000, $zeroBalanceWallet->wallet()->firstOrFail()->available_balance);
        $this->assertSame(250, $fundedWallet->wallet()->firstOrFail()->available_balance);
        $this->assertDatabaseCount('wallet_transactions', 3);

        $this->artisan('wallet:grant-demo-tokens', ['--amount' => 1000])
            ->expectsOutput('Eligible users: 0')
            ->expectsOutput('Granted users: 0')
            ->assertExitCode(0);

        $this->assertSame(1000, $withoutWallet->wallet()->firstOrFail()->available_balance);
        $this->assertSame(1000, $zeroBalanceWallet->wallet()->firstOrFail()->available_balance);
        $this->assertSame(250, $fundedWallet->wallet()->firstOrFail()->available_balance);
        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    public function test_it_supports_a_dry_run_without_creating_wallets_or_transactions(): void
    {
        User::factory()->count(2)->create();

        $this->artisan('wallet:grant-demo-tokens', ['--dry-run' => true])
            ->expectsOutput('Eligible users: 2')
            ->expectsOutput('Dry run complete. No tokens were granted.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('wallets', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
