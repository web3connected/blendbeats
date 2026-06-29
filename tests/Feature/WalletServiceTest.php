<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_service_creates_a_single_wallet_for_user(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);

        $first = $wallets->walletFor($user);
        $second = $wallets->walletFor($user);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('wallets', 1);
        $this->assertNotEmpty($first->uuid);
    }

    public function test_credit_debit_lock_and_unlock_write_ledger_snapshots(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);

        $wallets->credit($user, 100, 'token_purchase', [
            'description' => 'Starter token pack',
            'metadata' => ['source' => 'test'],
        ]);
        $wallets->debit($user, 25, 'promotion_purchase');
        $wallets->lock($user, 40, 'battle_entry_lock');
        $wallets->unlock($user, 10, 'battle_entry_refund');

        $wallet = $user->wallet()->firstOrFail();

        $this->assertSame(45, $wallet->available_balance);
        $this->assertSame(30, $wallet->locked_balance);
        $this->assertSame(100, $wallet->lifetime_earned);
        $this->assertSame(25, $wallet->lifetime_spent);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'token_purchase',
            'direction' => 'credit',
            'status' => 'completed',
            'amount' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'locked_balance_before' => 0,
            'locked_balance_after' => 0,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'battle_entry_lock',
            'direction' => 'lock',
            'status' => 'locked',
            'amount' => 40,
            'balance_before' => 75,
            'balance_after' => 35,
            'locked_balance_before' => 0,
            'locked_balance_after' => 40,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'battle_entry_refund',
            'direction' => 'unlock',
            'status' => 'released',
            'amount' => 10,
            'balance_before' => 35,
            'balance_after' => 45,
            'locked_balance_before' => 40,
            'locked_balance_after' => 30,
        ]);
    }

    public function test_wallet_service_rejects_invalid_or_insufficient_amounts(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);

        $this->expectException(InvalidArgumentException::class);
        $wallets->credit($user, 0, 'admin_adjustment');
    }

    public function test_wallet_service_rejects_debits_above_available_balance(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);

        $wallets->credit($user, 5, 'token_purchase');

        $this->expectException(RuntimeException::class);
        $wallets->debit($user, 10, 'promotion_purchase');
    }
}
