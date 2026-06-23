<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReward;
use App\Models\AffiliateRewardAudit;
use App\Models\User;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_affiliate_referral_and_reward_management_screens(): void
    {
        $admin = $this->superAdmin();
        ['affiliate' => $affiliate, 'referral' => $referral, 'reward' => $reward] = $this->affiliateFixture(createReward: true);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliates?search=Sponsor')
            ->assertOk()
            ->assertSee('Affiliate Management')
            ->assertSee('DJ Sponsor')
            ->assertSee('SPONSOR123')
            ->assertSee('Active Affiliates');

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliatereferrals?search=Listener')
            ->assertOk()
            ->assertSee('Affiliate Referral Management')
            ->assertSee('Referred Listener')
            ->assertSee('Awaiting subscription');

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliaterewards?status=pending')
            ->assertOk()
            ->assertSee('Affiliate Reward Management')
            ->assertSee('Future Incentive')
            ->assertSee('Pending');

        $this->assertSame(AffiliateAccount::STATUS_ACTIVE, $affiliate->fresh()->status);
        $this->assertSame(AffiliateReferral::STATUS_PENDING, $referral->fresh()->status);
        $this->assertSame(AffiliateReward::STATUS_PENDING, $reward->fresh()->status);
    }

    public function test_admin_can_update_affiliate_status(): void
    {
        $admin = $this->superAdmin();
        ['affiliate' => $affiliate] = $this->affiliateFixture();

        $this->actingAs($admin, 'admin')
            ->patch("/admin/admincenter/affiliates/{$affiliate->id}/status", [
                'status' => AffiliateAccount::STATUS_PAUSED,
            ])
            ->assertRedirect(route('admin.admincenter.affiliates.index', [
                'status' => AffiliateAccount::STATUS_PAUSED,
            ]));

        $affiliate->refresh();

        $this->assertSame(AffiliateAccount::STATUS_PAUSED, $affiliate->status);
        $this->assertNotNull($affiliate->paused_at);
        $this->assertNull($affiliate->banned_at);
    }

    public function test_admin_can_manually_qualify_referral_and_create_reward_records(): void
    {
        $admin = $this->superAdmin();
        ['referral' => $referral] = $this->affiliateFixture(createReward: false);

        $this->actingAs($admin, 'admin')
            ->patch("/admin/admincenter/affiliatereferrals/{$referral->id}/status", [
                'status' => AffiliateReferral::STATUS_QUALIFIED,
                'qualified_transaction_type' => 'admin',
                'qualified_transaction_id' => 'manual-txn-1',
            ])
            ->assertRedirect(route('admin.admincenter.affiliatereferrals.index', [
                'status' => AffiliateReferral::STATUS_QUALIFIED,
            ]));

        $referral->refresh();

        $this->assertSame(AffiliateReferral::STATUS_QUALIFIED, $referral->status);
        $this->assertSame('admin', $referral->qualified_transaction_type);
        $this->assertSame('manual-txn-1', $referral->qualified_transaction_id);
        $this->assertNotNull($referral->qualified_at);

        $this->assertDatabaseHas('affiliate_referral_events', [
            'affiliate_referral_id' => $referral->id,
            'event_source' => 'admin_manual',
            'transaction_type' => 'admin',
            'transaction_id' => 'manual-txn-1',
        ]);

        $this->assertDatabaseHas('affiliate_rewards', [
            'affiliate_referral_id' => $referral->id,
            'reward_type' => AffiliateReward::TYPE_MEMBERSHIP_CREDIT,
            'status' => AffiliateReward::STATUS_ISSUED,
            'source' => 'subscription_qualification',
            'membership_credit_days' => 30,
        ]);
    }

    public function test_admin_can_update_reward_status_with_audit_record(): void
    {
        $admin = $this->superAdmin();
        ['reward' => $reward] = $this->affiliateFixture(createReward: true);

        $this->actingAs($admin, 'admin')
            ->patch("/admin/admincenter/affiliaterewards/{$reward->id}/status", [
                'status' => AffiliateReward::STATUS_ISSUED,
                'issued_reference' => 'credit-001',
                'notes' => 'Issued through admin review.',
            ])
            ->assertRedirect(route('admin.admincenter.affiliaterewards.index', [
                'status' => AffiliateReward::STATUS_ISSUED,
            ]));

        $reward->refresh();

        $this->assertSame(AffiliateReward::STATUS_ISSUED, $reward->status);
        $this->assertSame('credit-001', $reward->issued_reference);
        $this->assertNotNull($reward->issued_at);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_ISSUED,
            'from_status' => AffiliateReward::STATUS_PENDING,
            'to_status' => AffiliateReward::STATUS_ISSUED,
            'actor_type' => Admin::class,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_admin_affiliate_management_requires_affiliate_permissions(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => 'password',
            'role' => 'manager',
            'is_active' => true,
        ]);
        $admin->syncRoles(['manager']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliates')
            ->assertForbidden();
    }

    public function test_admin_can_view_current_affiliate_program_settings(): void
    {
        config([
            'affiliate.reward_plan' => 'membership_credit',
            'affiliate.qualification_event' => 'subscription_qualified',
            'affiliate.membership_credit.tier' => 'dj_plus',
            'affiliate.membership_credit.duration_days' => 45,
            'affiliate.membership_credit.expires_after_months' => 9,
            'affiliate.notifications.expiring_soon_days' => 12,
        ]);

        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliatesettings')
            ->assertOk()
            ->assertSee('Affiliate Program Settings')
            ->assertSee('Membership Credit')
            ->assertSee('Subscription Qualified')
            ->assertSee('45 days')
            ->assertSee('9 months after issue')
            ->assertSee('12 days before expiration')
            ->assertSee('AFFILIATE_MEMBERSHIP_CREDIT_DAYS');
    }

    private function superAdmin(): Admin
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Affiliate Admin',
            'email' => 'affiliate-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        return $admin;
    }

    private function affiliateFixture(bool $createReward = false): array
    {
        $affiliateUser = User::factory()->create([
            'name' => 'DJ Sponsor',
            'email' => 'sponsor@example.com',
        ]);
        $referredUser = User::factory()->create([
            'name' => 'Referred Listener',
            'email' => 'listener@example.com',
        ]);

        $affiliate = AffiliateAccount::query()->create([
            'user_id' => $affiliateUser->id,
            'status' => AffiliateAccount::STATUS_ACTIVE,
            'display_name' => 'DJ Sponsor',
            'contact_email' => 'sponsor@example.com',
            'joined_at' => now()->subDays(3),
            'approved_at' => now()->subDays(3),
        ]);

        $code = AffiliateReferralCode::query()->create([
            'affiliate_account_id' => $affiliate->id,
            'code' => 'SPONSOR123',
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
        ]);

        $referral = AffiliateReferral::query()->create([
            'affiliate_account_id' => $affiliate->id,
            'affiliate_referral_code_id' => $code->id,
            'referred_user_id' => $referredUser->id,
            'status' => AffiliateReferral::STATUS_PENDING,
            'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
            'attributed_at' => now()->subDay(),
        ]);

        $reward = null;

        if ($createReward) {
            $reward = AffiliateReward::query()->create([
                'affiliate_account_id' => $affiliate->id,
                'affiliate_referral_id' => $referral->id,
                'reward_type' => AffiliateReward::TYPE_FUTURE_INCENTIVE,
                'source' => 'subscription_qualification',
                'status' => AffiliateReward::STATUS_PENDING,
                'quantity' => 1,
                'available_at' => now(),
            ]);
        }

        return [
            'affiliate' => $affiliate,
            'code' => $code,
            'referral' => $referral,
            'reward' => $reward,
        ];
    }
}
