<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AffiliateAccount;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReward;
use App\Models\AffiliateRewardAudit;
use App\Models\User;
use App\Services\AffiliateAnalyticsService;
use App\Services\AffiliatePayoutService;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliatePayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_payouts_are_disabled_by_default_for_active_affiliate_program(): void
    {
        ['user' => $user, 'account' => $account] = $this->affiliateFixture();
        $reward = $this->createCashReward($account, 5000, 'disabled-default');

        $this->assertFalse((bool) config('affiliate.payouts.enabled'));
        $this->assertNotContains('Affiliate Payouts', $this->adminMenuLabels());

        $this->actingAs($user)
            ->getJson('/api/account/affiliate/summary')
            ->assertOk()
            ->assertJsonPath('payouts_enabled', false)
            ->assertJsonPath('payout_balance.amount_cents', 0)
            ->assertJsonPath('payout_balance.reward_count', 0)
            ->assertJsonPath('payout_balance.can_request_payout', false)
            ->assertJsonPath('payout_statistics.total', 0)
            ->assertJsonPath('payout_history', []);

        $this->actingAs($user)
            ->getJson('/api/account/affiliate/payouts')
            ->assertOk()
            ->assertJsonPath('payouts_enabled', false)
            ->assertJsonPath('balance.amount_cents', 0)
            ->assertJsonPath('statistics.total', 0)
            ->assertJsonPath('payouts', []);

        $this->actingAs($user)
            ->postJson('/api/account/affiliate/payouts', [
                'payment_method' => 'paypal',
                'notes' => 'paypal@example.com',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Affiliate payouts are not enabled for the current program.');

        $this->assertDatabaseCount('affiliate_payouts', 0);
        $this->assertNull($reward->fresh()->affiliate_payout_id);
    }

    public function test_affiliate_can_view_payable_balance_and_request_payout(): void
    {
        $this->enablePayouts();

        ['user' => $user, 'account' => $account] = $this->affiliateFixture();
        $rewardOne = $this->createCashReward($account, 5000, 'cash-one');
        $rewardTwo = $this->createCashReward($account, 2500, 'cash-two');
        $this->createCashReward($account, 1000, 'pending-cash', AffiliateReward::STATUS_PENDING);
        $this->createMembershipReward($account);

        $this->actingAs($user)
            ->getJson('/api/account/affiliate/summary')
            ->assertOk()
            ->assertJsonPath('payout_balance.amount_cents', 7500)
            ->assertJsonPath('payout_balance.reward_count', 2)
            ->assertJsonPath('payout_balance.can_request_payout', true);

        $this->actingAs($user)
            ->postJson('/api/account/affiliate/payouts', [
                'payment_method' => 'paypal',
                'notes' => 'paypal@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Payout requested.')
            ->assertJsonPath('payout.status', AffiliatePayout::STATUS_REQUESTED)
            ->assertJsonPath('payout.amount_cents', 7500)
            ->assertJsonPath('payout.reward_count', 2)
            ->assertJsonPath('balance.amount_cents', 0);

        $payout = AffiliatePayout::query()->firstOrFail();

        $this->assertSame($account->id, $payout->affiliate_account_id);
        $this->assertSame($user->id, $payout->requested_by_user_id);
        $this->assertSame(7500, $payout->amount_cents);
        $this->assertSame($payout->id, $rewardOne->fresh()->affiliate_payout_id);
        $this->assertSame($payout->id, $rewardTwo->fresh()->affiliate_payout_id);

        $this->actingAs($user)
            ->getJson('/api/account/affiliate/payouts')
            ->assertOk()
            ->assertJsonPath('balance.amount_cents', 0)
            ->assertJsonPath('payouts.0.status', AffiliatePayout::STATUS_REQUESTED);
    }

    public function test_admin_can_approve_and_pay_payout_requests(): void
    {
        $this->enablePayouts();

        $admin = $this->superAdmin();
        ['account' => $account] = $this->affiliateFixture();
        $this->createCashReward($account, 4000, 'admin-pay-one');
        $this->createCashReward($account, 3500, 'admin-pay-two');
        $payout = app(AffiliatePayoutService::class)->requestPayout(
            account: $account,
            user: $account->user,
            paymentMethod: 'paypal',
            notes: 'admin-pay@example.com',
        );

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliatepayouts')
            ->assertOk()
            ->assertSee('Affiliate Payout Management')
            ->assertSee('USD 75.00')
            ->assertSee('Admin Pay');

        $this->actingAs($admin, 'admin')
            ->patch("/admin/admincenter/affiliatepayouts/{$payout->id}/status", [
                'status' => AffiliatePayout::STATUS_APPROVED,
                'notes' => 'Approved for payment.',
            ])
            ->assertRedirect(route('admin.admincenter.affiliatepayouts.index', [
                'status' => AffiliatePayout::STATUS_APPROVED,
            ]));

        $payout->refresh();

        $this->assertSame(AffiliatePayout::STATUS_APPROVED, $payout->status);
        $this->assertSame($admin->id, $payout->processed_by_admin_id);
        $this->assertNotNull($payout->approved_at);

        $this->actingAs($admin, 'admin')
            ->patch("/admin/admincenter/affiliatepayouts/{$payout->id}/status", [
                'status' => AffiliatePayout::STATUS_PAID,
                'payout_reference' => 'PAY-12345',
                'notes' => 'Paid through PayPal.',
            ])
            ->assertRedirect(route('admin.admincenter.affiliatepayouts.index', [
                'status' => AffiliatePayout::STATUS_PAID,
            ]));

        $payout->refresh();

        $this->assertSame(AffiliatePayout::STATUS_PAID, $payout->status);
        $this->assertSame('PAY-12345', $payout->payout_reference);
        $this->assertNotNull($payout->paid_at);

        $payout->rewards()->each(function (AffiliateReward $reward) use ($admin): void {
            $this->assertSame(AffiliateReward::STATUS_PAID, $reward->status);
            $this->assertNotNull($reward->paid_at);
            $this->assertDatabaseHas('affiliate_reward_audits', [
                'affiliate_reward_id' => $reward->id,
                'action' => AffiliateRewardAudit::ACTION_PAID,
                'to_status' => AffiliateReward::STATUS_PAID,
                'actor_type' => Admin::class,
                'actor_id' => $admin->id,
            ]);
        });
    }

    public function test_rejected_payout_releases_rewards_back_to_payable_balance(): void
    {
        $this->enablePayouts();

        $admin = $this->superAdmin();
        ['account' => $account] = $this->affiliateFixture();
        $reward = $this->createCashReward($account, 3000, 'reject-release');
        $payout = app(AffiliatePayoutService::class)->requestPayout(
            account: $account,
            user: $account->user,
            paymentMethod: 'manual',
        );

        $this->actingAs($admin, 'admin')
            ->patch("/admin/admincenter/affiliatepayouts/{$payout->id}/status", [
                'status' => AffiliatePayout::STATUS_REJECTED,
                'rejection_reason' => 'missing_payment_details',
            ])
            ->assertRedirect(route('admin.admincenter.affiliatepayouts.index', [
                'status' => AffiliatePayout::STATUS_REJECTED,
            ]));

        $payout->refresh();
        $reward->refresh();
        $balance = app(AffiliatePayoutService::class)->payableBalance($account);

        $this->assertSame(AffiliatePayout::STATUS_REJECTED, $payout->status);
        $this->assertSame('missing_payment_details', $payout->rejection_reason);
        $this->assertNull($reward->affiliate_payout_id);
        $this->assertSame(AffiliateReward::STATUS_APPROVED, $reward->status);
        $this->assertSame(3000, $balance['amount_cents']);
    }

    public function test_payout_analytics_are_available_in_service_and_admin_api(): void
    {
        $this->enablePayouts();

        $admin = $this->superAdmin();
        ['account' => $account] = $this->affiliateFixture();
        $this->createCashReward($account, 4500, 'paid-analytics');
        $payout = app(AffiliatePayoutService::class)->requestPayout(
            account: $account,
            user: $account->user,
            paymentMethod: 'paypal',
        );
        app(AffiliatePayoutService::class)->markPaid($payout, $admin, 'ANALYTICS-PAYOUT');
        $this->createCashReward($account, 2000, 'payable-analytics');

        $report = app(AffiliateAnalyticsService::class)->report();

        $this->assertSame(2000, $report['statistics']['total_payable_balance_cents']);
        $this->assertSame(1, $report['statistics']['total_payouts_requested']);
        $this->assertSame(1, $report['statistics']['total_payouts_paid']);
        $this->assertSame(4500, $report['statistics']['total_payout_amount_paid_cents']);
        $this->assertSame(2000, $report['payouts']['payable_balance_cents']);
        $this->assertSame(4500, $report['payouts']['paid_amount_cents']);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/affiliate-analytics')
            ->assertOk()
            ->assertJsonPath('statistics.total_payable_balance_cents', 2000)
            ->assertJsonPath('payouts.paid_amount_cents', 4500);
    }

    private function enablePayouts(): void
    {
        config(['affiliate.payouts.enabled' => true]);
    }

    private function adminMenuLabels(): array
    {
        return collect(config('adminlte.menu'))
            ->flatMap(fn (array $item): array => $item['submenu'] ?? [$item])
            ->pluck('text')
            ->filter()
            ->values()
            ->all();
    }

    private function affiliateFixture(): array
    {
        $user = User::factory()->create([
            'name' => 'Admin Pay',
            'email' => 'admin-pay@example.com',
        ]);
        $account = AffiliateAccount::query()->create([
            'user_id' => $user->id,
            'status' => AffiliateAccount::STATUS_ACTIVE,
            'display_name' => 'Admin Pay',
            'contact_email' => $user->email,
            'joined_at' => now(),
            'approved_at' => now(),
        ]);

        AffiliateReferralCode::query()->create([
            'affiliate_account_id' => $account->id,
            'code' => 'PAYOUTS',
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
        ]);

        return [
            'user' => $user,
            'account' => $account->fresh('user'),
        ];
    }

    private function createCashReward(
        AffiliateAccount $account,
        int $amountCents,
        string $source,
        string $status = AffiliateReward::STATUS_APPROVED,
    ): AffiliateReward {
        $referral = $this->createReferral($account, $source);

        return AffiliateReward::query()->create([
            'affiliate_account_id' => $account->id,
            'affiliate_referral_id' => $referral->id,
            'reward_type' => AffiliateReward::TYPE_CASH_COMMISSION,
            'source' => $source,
            'status' => $status,
            'amount_cents' => $amountCents,
            'currency' => AffiliatePayoutService::DEFAULT_CURRENCY,
            'quantity' => 1,
            'available_at' => now()->subDay(),
            'approved_at' => $status === AffiliateReward::STATUS_APPROVED ? now()->subHour() : null,
        ]);
    }

    private function createMembershipReward(AffiliateAccount $account): AffiliateReward
    {
        $referral = $this->createReferral($account, 'membership');

        return AffiliateReward::query()->create([
            'affiliate_account_id' => $account->id,
            'affiliate_referral_id' => $referral->id,
            'reward_type' => AffiliateReward::TYPE_MEMBERSHIP_CREDIT,
            'source' => 'membership',
            'status' => AffiliateReward::STATUS_ISSUED,
            'quantity' => 1,
            'membership_credit_days' => 30,
            'available_at' => now()->subDay(),
            'issued_at' => now()->subHour(),
            'expires_at' => now()->addMonths(12),
        ]);
    }

    private function createReferral(AffiliateAccount $account, string $suffix): AffiliateReferral
    {
        $code = $account->defaultReferralCode()->firstOrFail();
        $user = User::factory()->create([
            'email' => 'payout-'.$suffix.'@example.com',
        ]);

        return AffiliateReferral::query()->create([
            'affiliate_account_id' => $account->id,
            'affiliate_referral_code_id' => $code->id,
            'referred_user_id' => $user->id,
            'status' => AffiliateReferral::STATUS_QUALIFIED,
            'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
            'attributed_at' => now()->subDays(2),
            'qualified_at' => now()->subDay(),
        ]);
    }

    private function superAdmin(): Admin
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Payout Admin',
            'email' => 'payout-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        return $admin;
    }
}
