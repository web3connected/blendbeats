<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/wallet')
            ->assertUnauthorized();
    }

    public function test_wallet_endpoint_creates_and_returns_current_user_wallet(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('wallet.available_balance', 0)
            ->assertJsonPath('wallet.locked_balance', 0)
            ->assertJsonPath('wallet.total_balance', 0)
            ->assertJsonPath('wallet.status', 'active')
            ->assertJsonCount(0, 'transactions');

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'available_balance' => 0,
            'locked_balance' => 0,
        ]);
    }

    public function test_wallet_endpoint_returns_recent_transactions(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);

        $wallets->credit($user, 100, 'token_purchase');
        $wallets->lock($user, 20, 'battle_entry_lock');

        $this->actingAs($user)
            ->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('wallet.available_balance', 80)
            ->assertJsonPath('wallet.locked_balance', 20)
            ->assertJsonCount(2, 'transactions')
            ->assertJsonPath('transactions.0.type', 'battle_entry_lock')
            ->assertJsonPath('transactions.0.direction', 'lock');
    }
}
