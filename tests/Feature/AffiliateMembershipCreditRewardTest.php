<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\AffiliateReward;
use App\Models\AffiliateRewardAudit;
use App\Models\User;
use App\Services\AffiliateReferralTrackingService;
use App\Services\AffiliateRewardService;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateMembershipCreditRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_referred_subscription_payment_creates_issued_membership_credit(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        config(['billing.paypal.plans.dj_plus' => 'test-plan-id']);
        $this->withoutVite();

        $code = $this->createReferralCode('MEMBER-CREDIT');
        $referredUser = $this->registerReferredUser($code->code, 'membership-credit@example.com');

        $this->actingAs($referredUser)
            ->postJson('/api/billing/paypal/subscription-approved', [
                'subscriptionID' => 'I-membership-credit',
            ])
            ->assertOk()
            ->assertJsonPath('referral_qualification.status', 'qualified');

        $reward = $referredUser->affiliateReferral()->firstOrFail()->rewards()->firstOrFail();

        $this->assertSame(AffiliateReward::TYPE_MEMBERSHIP_CREDIT, $reward->reward_type);
        $this->assertSame(AffiliateReward::STATUS_ISSUED, $reward->status);
        $this->assertSame(30, $reward->membership_credit_days);
        $this->assertSame(now()->addMonthsNoOverflow(12)->toDateTimeString(), $reward->expires_at->toDateTimeString());
        $this->assertSame('membership-credit-'.$reward->id, $reward->issued_reference);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_CREATED,
            'to_status' => AffiliateReward::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_ISSUED,
            'from_status' => AffiliateReward::STATUS_PENDING,
            'to_status' => AffiliateReward::STATUS_ISSUED,
        ]);
    }

    public function test_membership_credit_redemption_stacks_free_membership_time(): void
    {
        $this->travelTo(now()->setTime(9, 30));

        ['user' => $affiliate, 'account' => $account] = $this->createAffiliateAccount('STACK-CREDIT');
        $firstReward = $this->createMembershipCreditReward($account, 'stack-one@example.com');
        $secondReward = $this->createMembershipCreditReward($account, 'stack-two@example.com');

        $this->actingAs($affiliate)
            ->postJson("/api/account/affiliate/rewards/{$firstReward->id}/redeem")
            ->assertOk()
            ->assertJsonPath('message', 'Membership credit redeemed.')
            ->assertJsonPath('subscription.plan', 'dj_plus');

        $affiliate->refresh();
        $this->assertSame(now()->addDays(30)->toDateTimeString(), $affiliate->comped_subscription_expires_at->toDateTimeString());
        $this->assertSame(AffiliateReward::STATUS_REDEEMED, $firstReward->fresh()->status);

        $this->actingAs($affiliate)
            ->postJson("/api/account/affiliate/rewards/{$secondReward->id}/redeem")
            ->assertOk()
            ->assertJsonPath('subscription.plan', 'dj_plus');

        $affiliate->refresh();
        $this->assertSame(now()->addDays(60)->toDateTimeString(), $affiliate->comped_subscription_expires_at->toDateTimeString());
        $this->assertSame(AffiliateReward::STATUS_REDEEMED, $secondReward->fresh()->status);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $firstReward->id,
            'action' => AffiliateRewardAudit::ACTION_REDEEMED,
            'from_status' => AffiliateReward::STATUS_ISSUED,
            'to_status' => AffiliateReward::STATUS_REDEEMED,
            'actor_type' => User::class,
            'actor_id' => $affiliate->id,
        ]);
    }

    public function test_expired_membership_credit_cannot_be_redeemed_and_is_marked_expired(): void
    {
        $this->travelTo(now()->setTime(10, 15));

        ['user' => $affiliate, 'account' => $account] = $this->createAffiliateAccount('EXPIRED-CREDIT');
        $reward = $this->createMembershipCreditReward($account, 'expired-credit@example.com', [
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($affiliate)
            ->postJson("/api/account/affiliate/rewards/{$reward->id}/redeem")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reward');

        $reward->refresh();

        $this->assertSame(AffiliateReward::STATUS_EXPIRED, $reward->status);
        $this->assertNull($reward->redeemed_at);
        $this->assertNull($affiliate->fresh()->comped_subscription_expires_at);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_EXPIRED,
            'from_status' => AffiliateReward::STATUS_ISSUED,
            'to_status' => AffiliateReward::STATUS_EXPIRED,
        ]);
    }

    public function test_dashboard_and_admin_reward_views_show_membership_credit_expiration(): void
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

        ['user' => $affiliate, 'account' => $account] = $this->createAffiliateAccount('VISIBLE-CREDIT');
        $reward = $this->createMembershipCreditReward($account, 'visible-credit@example.com');

        $this->actingAs($affiliate)
            ->getJson('/api/account/affiliate/rewards')
            ->assertOk()
            ->assertJsonPath('rewards.0.reward_type', AffiliateReward::TYPE_MEMBERSHIP_CREDIT)
            ->assertJsonPath('rewards.0.membership_credit_days', 30)
            ->assertJsonPath('rewards.0.expires_at', $reward->expires_at->toISOString())
            ->assertJsonPath('rewards.0.can_redeem', true)
            ->assertJsonPath('statistics.membership_credits_available', 1)
            ->assertJsonPath('statistics.membership_credit_days_available', 30);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliaterewards?search=VISIBLE-CREDIT')
            ->assertOk()
            ->assertSee('Affiliate Reward Management')
            ->assertSee('30 days')
            ->assertSee('Redeem by '.$reward->expires_at->format('M j, Y'));
    }

    private function createMembershipCreditReward(AffiliateAccount $account, string $email, array $attributes = []): AffiliateReward
    {
        $referral = $this->createQualifiedReferral($account, $email);

        return app(AffiliateRewardService::class)
            ->createMembershipCreditForQualifiedReferral($referral, $attributes);
    }

    private function createQualifiedReferral(AffiliateAccount $account, string $email): AffiliateReferral
    {
        $code = $account->defaultReferralCode()->firstOrFail();
        $referredUser = User::factory()->create(['email' => $email]);

        return AffiliateReferral::query()->create([
            'affiliate_account_id' => $account->id,
            'affiliate_referral_code_id' => $code->id,
            'referred_user_id' => $referredUser->id,
            'status' => AffiliateReferral::STATUS_QUALIFIED,
            'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
            'attributed_at' => now(),
            'qualified_at' => now(),
            'qualified_transaction_type' => 'paypal',
            'qualified_transaction_id' => 'I-credit-'.$referredUser->id,
        ]);
    }

    private function registerReferredUser(string $code, string $email): User
    {
        $this->get('/register?ref='.$code)
            ->assertOk()
            ->assertSessionHas(AffiliateReferralTrackingService::SESSION_KEY);

        $visit = AffiliateReferralVisit::query()->latest('id')->firstOrFail();

        $this->postJson('/api/auth/register', [
            'name' => 'Membership Credit Referral',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('referral_context.referral_visit_id', $visit->id)
            ->assertJsonPath('referral_attribution.status', 'pending');

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function createReferralCode(string $code): AffiliateReferralCode
    {
        return $this->createAffiliateAccount($code)['code'];
    }

    private function createAffiliateAccount(string $code): array
    {
        $user = User::factory()->create([
            'media_storage_tier' => 'free',
        ]);

        $account = AffiliateAccount::query()->create([
            'user_id' => $user->id,
            'status' => AffiliateAccount::STATUS_ACTIVE,
            'display_name' => $user->name,
            'contact_email' => $user->email,
            'joined_at' => now(),
            'approved_at' => now(),
        ]);

        $referralCode = AffiliateReferralCode::query()->create([
            'affiliate_account_id' => $account->id,
            'code' => $code,
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
            'starts_at' => now()->subMinute(),
        ]);

        return [
            'user' => $user,
            'account' => $account,
            'code' => $referralCode,
        ];
    }
}
