<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\BattleTestingWalletSeeder;
use Database\Seeders\BattleDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_demo_seeder_creates_djs_fans_wallets_and_demo_battles(): void
    {
        $this->seed(BattleDemoSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'dj.neon@example.com',
            'name' => 'Neon Lux',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'fan.maya@example.com',
            'name' => 'Maya Rhodes',
        ]);
        $this->assertDatabaseHas('dj_profiles', [
            'handle' => 'dj-neon-lux',
            'battle_enabled' => true,
            'profile_status' => 'active',
            'visibility' => 'public',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'demo_seed_tokens',
            'direction' => 'credit',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => BattleTestingWalletSeeder::TRANSACTION_TYPE,
            'direction' => 'credit',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('dj_battles', [
            'title' => 'Demo Battle: Neon vs Cipher',
            'status' => 'completed',
            'stake_amount' => 0,
        ]);

        $this->assertDatabaseCount('dj_battles', 6);
        $this->assertSame(14, User::query()
            ->where(fn ($query) => $query
                ->where('email', 'like', 'dj.%@example.com')
                ->orWhere('email', 'like', 'fan.%@example.com'))
            ->whereHas('wallet', fn ($query) => $query->whereRaw('(available_balance + locked_balance) >= 1000'))
            ->count());

        $this->seed(BattleDemoSeeder::class);

        $this->assertDatabaseCount('dj_battles', 6);
        $this->assertSame(14, WalletTransaction::query()
            ->where('type', BattleTestingWalletSeeder::TRANSACTION_TYPE)
            ->count());
        $this->assertSame(14, User::query()
            ->where(fn ($query) => $query
                ->where('email', 'like', 'dj.%@example.com')
                ->orWhere('email', 'like', 'fan.%@example.com'))
            ->count());
    }
}
