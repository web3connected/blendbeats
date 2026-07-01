<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BetaTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_beta_test_tokens_and_audit_transactions(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Token Admin',
            'email' => 'tokens-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Beta Tester',
            'email' => 'beta-tester@example.com',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/beta-tokens?search=beta-tester')
            ->assertOk()
            ->assertSee('Beta Token Management')
            ->assertSee('Beta Tester');

        $this->actingAs($admin, 'admin')
            ->post("/admin/admincenter/beta-tokens/{$user->id}/grant", [
                'amount' => 250,
                'notes' => 'Manual beta grant',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletService::TYPE_BETA_GRANT,
            'amount' => 250,
            'created_by_admin_id' => $admin->id,
            'description' => 'Manual beta grant',
        ]);
        $this->assertSame(250, $user->wallet()->firstOrFail()->available_balance);

        $this->actingAs($admin, 'admin')
            ->post("/admin/admincenter/beta-tokens/{$user->id}/remove", [
                'amount' => 100,
                'notes' => 'Manual beta removal',
            ])
            ->assertRedirect();

        $this->assertSame(150, $user->wallet()->firstOrFail()->available_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletService::TYPE_BETA_ADJUSTMENT,
            'amount' => 100,
            'created_by_admin_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->post("/admin/admincenter/beta-tokens/{$user->id}/reset", [
                'amount' => 500,
                'notes' => 'Reset to default',
            ])
            ->assertRedirect();

        $wallet = $user->wallet()->firstOrFail();
        $this->assertSame(500, $wallet->available_balance);
        $this->assertSame(0, $wallet->locked_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletService::TYPE_ADMIN_CORRECTION,
            'created_by_admin_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->post("/admin/admincenter/beta-tokens/{$user->id}/status", [
                'status' => 'suspended',
            ])
            ->assertRedirect();

        $this->assertSame('suspended', $user->wallet()->firstOrFail()->status);
    }
}
