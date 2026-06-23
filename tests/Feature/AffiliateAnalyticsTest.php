<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\AffiliateReward;
use App\Models\User;
use App\Services\AffiliateAnalyticsService;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_analytics_service_returns_program_statistics_and_leaderboard(): void
    {
        $this->seedAnalyticsFixture();

        $report = app(AffiliateAnalyticsService::class)->report();

        $this->assertSame(2, $report['statistics']['total_affiliates']);
        $this->assertSame(1, $report['statistics']['active_affiliates']);
        $this->assertSame(5, $report['statistics']['total_referral_visits']);
        $this->assertSame(4, $report['statistics']['total_attributed_signups']);
        $this->assertSame(3, $report['statistics']['total_qualified_referrals']);
        $this->assertSame(3, $report['statistics']['total_membership_credits_issued']);
        $this->assertSame(1, $report['statistics']['total_membership_credits_redeemed']);
        $this->assertSame(1, $report['statistics']['total_membership_credits_expired']);

        $this->assertSame(80.0, $report['conversion_rates']['visit_to_signup_rate']);
        $this->assertSame(75.0, $report['conversion_rates']['signup_to_qualified_rate']);
        $this->assertSame(60.0, $report['conversion_rates']['visit_to_qualified_rate']);

        $this->assertSame('Top Affiliate', $report['top_affiliates'][0]['display_name']);
        $this->assertSame(2, $report['top_affiliates'][0]['qualified_referrals']);
        $this->assertSame('Second Affiliate', $report['top_affiliates'][1]['display_name']);
    }

    public function test_admin_affiliate_analytics_api_returns_statistics(): void
    {
        $this->seedAnalyticsFixture();
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/affiliate-analytics?leaderboard_limit=1')
            ->assertOk()
            ->assertJsonPath('statistics.total_affiliates', 2)
            ->assertJsonPath('statistics.active_affiliates', 1)
            ->assertJsonPath('statistics.total_referral_visits', 5)
            ->assertJsonPath('statistics.total_attributed_signups', 4)
            ->assertJsonPath('statistics.total_qualified_referrals', 3)
            ->assertJsonPath('statistics.total_membership_credits_issued', 3)
            ->assertJsonPath('statistics.total_membership_credits_redeemed', 1)
            ->assertJsonPath('statistics.total_membership_credits_expired', 1)
            ->assertJsonPath('conversion_rates.visit_to_signup_rate', 80)
            ->assertJsonPath('conversion_rates.signup_to_qualified_rate', 75)
            ->assertJsonPath('conversion_rates.visit_to_qualified_rate', 60)
            ->assertJsonCount(1, 'top_affiliates')
            ->assertJsonPath('top_affiliates.0.display_name', 'Top Affiliate')
            ->assertJsonPath('top_affiliates.0.qualified_referrals', 2);
    }

    public function test_admin_can_view_affiliate_analytics_screen(): void
    {
        $this->seedAnalyticsFixture();
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliateanalytics')
            ->assertOk()
            ->assertSee('Affiliate Analytics')
            ->assertSee('Total Referral Visits')
            ->assertSee('Attributed Signups')
            ->assertSee('Credits Redeemed')
            ->assertSee('Conversion Rates')
            ->assertSee('Top Affiliates Leaderboard')
            ->assertSee('Top Affiliate')
            ->assertSee('Second Affiliate')
            ->assertSee('80.00%');
    }

    public function test_affiliate_analytics_api_requires_affiliate_view_permission(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Manager',
            'email' => 'analytics-manager@example.com',
            'password' => 'password',
            'role' => 'manager',
            'is_active' => true,
        ]);
        $admin->syncRoles(['manager']);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/affiliate-analytics')
            ->assertForbidden();
    }

    private function seedAnalyticsFixture(): void
    {
        $topAccount = $this->createAffiliateAccount('Top Affiliate', 'TOPAFF', AffiliateAccount::STATUS_PAUSED);
        $secondAccount = $this->createAffiliateAccount('Second Affiliate', 'SECONDAFF', AffiliateAccount::STATUS_ACTIVE);

        $this->createVisits($topAccount, 3);
        $this->createVisits($secondAccount, 2);

        $topReferralOne = $this->createReferral($topAccount, 'top-one@example.com', AffiliateReferral::STATUS_QUALIFIED);
        $topReferralTwo = $this->createReferral($topAccount, 'top-two@example.com', AffiliateReferral::STATUS_QUALIFIED);
        $this->createReferral($topAccount, 'top-pending@example.com', AffiliateReferral::STATUS_PENDING);
        $secondReferral = $this->createReferral($secondAccount, 'second-one@example.com', AffiliateReferral::STATUS_QUALIFIED);

        $this->createMembershipCredit($topReferralOne, AffiliateReward::STATUS_ISSUED, 'top-issued');
        $this->createMembershipCredit($topReferralTwo, AffiliateReward::STATUS_REDEEMED, 'top-redeemed');
        $this->createMembershipCredit($secondReferral, AffiliateReward::STATUS_EXPIRED, 'second-expired');
    }

    private function createAffiliateAccount(string $name, string $code, string $status): AffiliateAccount
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => str($name)->slug()->append('@example.com')->toString(),
        ]);

        $account = AffiliateAccount::query()->create([
            'user_id' => $user->id,
            'status' => $status,
            'display_name' => $name,
            'contact_email' => $user->email,
            'joined_at' => now()->subDays(10),
            'approved_at' => now()->subDays(10),
        ]);

        AffiliateReferralCode::query()->create([
            'affiliate_account_id' => $account->id,
            'code' => $code,
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
            'starts_at' => now()->subDays(10),
        ]);

        return $account;
    }

    private function createVisits(AffiliateAccount $account, int $count): void
    {
        $code = $account->defaultReferralCode()->firstOrFail();

        foreach (range(1, $count) as $index) {
            AffiliateReferralVisit::query()->create([
                'affiliate_account_id' => $account->id,
                'affiliate_referral_code_id' => $code->id,
                'visitor_id' => 'analytics-'.$account->id.'-'.$index,
                'landing_url' => '/register?ref='.$code->code,
                'visited_at' => now()->subDays($index),
            ]);
        }
    }

    private function createReferral(AffiliateAccount $account, string $email, string $status): AffiliateReferral
    {
        $code = $account->defaultReferralCode()->firstOrFail();
        $referredUser = User::factory()->create([
            'email' => $email,
        ]);

        return AffiliateReferral::query()->create([
            'affiliate_account_id' => $account->id,
            'affiliate_referral_code_id' => $code->id,
            'referred_user_id' => $referredUser->id,
            'status' => $status,
            'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
            'attributed_at' => now()->subDays(2),
            'qualified_at' => $status === AffiliateReferral::STATUS_QUALIFIED ? now()->subDay() : null,
            'qualified_transaction_type' => $status === AffiliateReferral::STATUS_QUALIFIED ? 'paypal' : null,
            'qualified_transaction_id' => $status === AffiliateReferral::STATUS_QUALIFIED ? 'I-analytics-'.$referredUser->id : null,
        ]);
    }

    private function createMembershipCredit(AffiliateReferral $referral, string $status, string $source): AffiliateReward
    {
        return AffiliateReward::query()->create([
            'affiliate_account_id' => $referral->affiliate_account_id,
            'affiliate_referral_id' => $referral->id,
            'reward_type' => AffiliateReward::TYPE_MEMBERSHIP_CREDIT,
            'source' => $source,
            'status' => $status,
            'quantity' => 1,
            'membership_credit_days' => 30,
            'available_at' => now()->subDay(),
            'issued_at' => now()->subDay(),
            'redeemed_at' => $status === AffiliateReward::STATUS_REDEEMED ? now() : null,
            'expires_at' => $status === AffiliateReward::STATUS_EXPIRED ? now()->subDay() : now()->addMonths(12),
            'issued_reference' => 'analytics-'.$source,
        ]);
    }

    private function superAdmin(): Admin
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Analytics Admin',
            'email' => 'analytics-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        return $admin;
    }
}
