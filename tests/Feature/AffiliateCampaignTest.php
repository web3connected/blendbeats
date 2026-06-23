<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AffiliateAccount;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\AffiliateReward;
use App\Models\User;
use App\Services\AffiliateAnalyticsService;
use App\Services\AffiliateReferralTrackingService;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_campaigns_and_assign_referral_codes(): void
    {
        $admin = $this->superAdmin();
        $code = $this->createReferralCode('SUMMER-DJ');

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliatecampaigns')
            ->assertOk()
            ->assertSee('Affiliate Campaigns')
            ->assertSee('Referral Code Campaign Assignment')
            ->assertSee('SUMMER-DJ');

        $this->actingAs($admin, 'admin')
            ->post('/admin/admincenter/affiliatecampaigns', [
                'name' => 'Summer Push',
                'slug' => 'summer-push',
                'status' => AffiliateCampaign::STATUS_ACTIVE,
                'description' => 'Seasonal membership push.',
                'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.admincenter.affiliatecampaigns.index'));

        $campaign = AffiliateCampaign::query()->where('slug', 'summer-push')->firstOrFail();

        $this->assertSame('Summer Push', $campaign->name);
        $this->assertSame(AffiliateCampaign::STATUS_ACTIVE, $campaign->status);
        $this->assertSame($admin->id, $campaign->created_by_admin_id);

        $this->actingAs($admin, 'admin')
            ->patch("/admin/admincenter/affiliatecodes/{$code->id}/campaign", [
                'affiliate_campaign_id' => $campaign->id,
            ])
            ->assertRedirect(route('admin.admincenter.affiliatecampaigns.index'));

        $this->assertSame($campaign->id, $code->fresh()->affiliate_campaign_id);

        $this->actingAs($admin, 'admin')
            ->patch("/admin/admincenter/affiliatecampaigns/{$campaign->id}", [
                'name' => 'Summer Push Updated',
                'slug' => 'summer-push-updated',
                'status' => AffiliateCampaign::STATUS_PAUSED,
                'description' => 'Paused seasonal membership push.',
                'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.admincenter.affiliatecampaigns.index'));

        $campaign->refresh();

        $this->assertSame('Summer Push Updated', $campaign->name);
        $this->assertSame('summer-push-updated', $campaign->slug);
        $this->assertSame(AffiliateCampaign::STATUS_PAUSED, $campaign->status);
    }

    public function test_active_campaign_assignment_is_captured_on_visit_and_signup_attribution(): void
    {
        $this->withoutVite();

        $campaign = $this->createCampaign('Launch Week', AffiliateCampaign::STATUS_ACTIVE);
        $code = $this->createReferralCode('LAUNCH-WEEK', $campaign);

        $this->get('/register?ref=LAUNCH-WEEK')
            ->assertOk()
            ->assertSessionHas(AffiliateReferralTrackingService::SESSION_KEY);

        $context = session(AffiliateReferralTrackingService::SESSION_KEY);
        $visit = AffiliateReferralVisit::query()->firstOrFail();

        $this->assertSame($campaign->id, $context['affiliate_campaign_id']);
        $this->assertSame($campaign->slug, $context['campaign_slug']);
        $this->assertSame($campaign->id, $visit->affiliate_campaign_id);

        $this->postJson('/api/auth/register', [
            'name' => 'Campaign Signup',
            'email' => 'campaign-signup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('referral_context.affiliate_campaign_id', $campaign->id)
            ->assertJsonPath('referral_context.campaign_slug', $campaign->slug)
            ->assertJsonPath('referral_attribution.referral_code', $code->code);

        $referredUser = User::query()->where('email', 'campaign-signup@example.com')->firstOrFail();

        $this->assertDatabaseHas('affiliate_referrals', [
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_campaign_id' => $campaign->id,
            'affiliate_referral_code_id' => $code->id,
            'affiliate_referral_visit_id' => $visit->id,
            'referred_user_id' => $referredUser->id,
            'status' => AffiliateReferral::STATUS_PENDING,
        ]);
    }

    public function test_paused_campaign_referral_code_is_not_captured(): void
    {
        $this->withoutVite();

        $campaign = $this->createCampaign('Paused Campaign', AffiliateCampaign::STATUS_PAUSED);
        $this->createReferralCode('PAUSED-CODE', $campaign);

        $this->get('/register?ref=PAUSED-CODE')
            ->assertOk()
            ->assertSessionMissing(AffiliateReferralTrackingService::SESSION_KEY)
            ->assertCookieMissing(AffiliateReferralTrackingService::COOKIE_NAME);

        $this->assertDatabaseCount('affiliate_referral_visits', 0);
    }

    public function test_campaign_analytics_are_available_in_service_admin_screen_and_api(): void
    {
        $campaign = $this->createCampaign('Influencer Push', AffiliateCampaign::STATUS_ACTIVE);
        $code = $this->createReferralCode('INFLUENCE', $campaign);
        $referral = $this->createCampaignPerformanceFixture($campaign, $code);

        $report = app(AffiliateAnalyticsService::class)->report();

        $this->assertSame('Influencer Push', $report['campaigns'][0]['name']);
        $this->assertSame(2, $report['campaigns'][0]['referral_visits']);
        $this->assertSame(1, $report['campaigns'][0]['attributed_signups']);
        $this->assertSame(1, $report['campaigns'][0]['qualified_referrals']);
        $this->assertSame(1, $report['campaigns'][0]['membership_credits_issued']);
        $this->assertSame(50.0, $report['campaigns'][0]['visit_to_signup_rate']);
        $this->assertSame($campaign->id, $referral->affiliate_campaign_id);

        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliateanalytics')
            ->assertOk()
            ->assertSee('Campaign Performance')
            ->assertSee('Influencer Push');

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/affiliate-analytics')
            ->assertOk()
            ->assertJsonPath('campaigns.0.name', 'Influencer Push')
            ->assertJsonPath('campaigns.0.referral_visits', 2)
            ->assertJsonPath('campaigns.0.qualified_referrals', 1);
    }

    private function createCampaign(string $name, string $status): AffiliateCampaign
    {
        return AffiliateCampaign::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'status' => $status,
            'description' => $name.' campaign.',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    private function createReferralCode(
        string $code,
        ?AffiliateCampaign $campaign = null,
    ): AffiliateReferralCode {
        $email = str($code)->slug()->append('@example.com')->toString();
        $user = User::factory()->create([
            'email' => $email,
        ]);
        $account = AffiliateAccount::query()->create([
            'user_id' => $user->id,
            'status' => AffiliateAccount::STATUS_ACTIVE,
            'display_name' => $user->name,
            'contact_email' => $user->email,
            'joined_at' => now(),
            'approved_at' => now(),
        ]);

        return AffiliateReferralCode::query()->create([
            'affiliate_account_id' => $account->id,
            'affiliate_campaign_id' => $campaign?->id,
            'code' => $code,
            'label' => 'Campaign referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
            'starts_at' => now()->subMinute(),
        ]);
    }

    private function createCampaignPerformanceFixture(
        AffiliateCampaign $campaign,
        AffiliateReferralCode $code,
    ): AffiliateReferral {
        foreach (range(1, 2) as $index) {
            AffiliateReferralVisit::query()->create([
                'affiliate_account_id' => $code->affiliate_account_id,
                'affiliate_campaign_id' => $campaign->id,
                'affiliate_referral_code_id' => $code->id,
                'visitor_id' => 'campaign-visitor-'.$index,
                'landing_url' => '/register?ref='.$code->code,
                'visited_at' => now()->subDays($index),
            ]);
        }

        $referredUser = User::factory()->create([
            'email' => 'campaign-qualified@example.com',
        ]);

        $referral = AffiliateReferral::query()->create([
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_campaign_id' => $campaign->id,
            'affiliate_referral_code_id' => $code->id,
            'referred_user_id' => $referredUser->id,
            'status' => AffiliateReferral::STATUS_QUALIFIED,
            'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
            'attributed_at' => now()->subDays(2),
            'qualified_at' => now()->subDay(),
            'qualified_transaction_type' => 'stripe',
            'qualified_transaction_id' => 'campaign-txn-1',
        ]);

        AffiliateReward::query()->create([
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_referral_id' => $referral->id,
            'reward_type' => AffiliateReward::TYPE_MEMBERSHIP_CREDIT,
            'source' => 'subscription_qualification',
            'status' => AffiliateReward::STATUS_ISSUED,
            'quantity' => 1,
            'membership_credit_days' => 30,
            'available_at' => now()->subDay(),
            'issued_at' => now()->subDay(),
            'expires_at' => now()->addMonths(12),
            'issued_reference' => 'campaign-credit-1',
        ]);

        return $referral;
    }

    private function superAdmin(): Admin
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Campaign Admin',
            'email' => 'campaign-admin-'.str()->random(8).'@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        return $admin;
    }
}
