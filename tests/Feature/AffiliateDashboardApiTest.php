<?php

namespace Tests\Feature;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\AffiliateReward;
use App\Models\User;
use App\Services\AffiliateRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_summary_returns_account_statistics_and_activity(): void
    {
        $affiliate = User::factory()->create();
        $account = $this->createAffiliateAccount($affiliate, 'DASHBOARD');
        $pendingReferral = $this->createReferral($account, 'pending-user@example.com', AffiliateReferral::STATUS_PENDING);
        $qualifiedReferral = $this->createReferral($account, 'qualified-user@example.com', AffiliateReferral::STATUS_QUALIFIED);

        app(AffiliateRewardService::class)->createForQualifiedReferral($qualifiedReferral);

        $this->actingAs($affiliate)
            ->getJson('/api/account/affiliate/summary')
            ->assertOk()
            ->assertJsonPath('affiliate_account.id', $account->id)
            ->assertJsonPath('affiliate_account.referral_code', 'DASHBOARD')
            ->assertJsonPath('referral_statistics.visits', 2)
            ->assertJsonPath('referral_statistics.signups', 2)
            ->assertJsonPath('qualification_statistics.pending', 1)
            ->assertJsonPath('qualification_statistics.qualified', 1)
            ->assertJsonPath('reward_statistics.total', 1)
            ->assertJsonPath('reward_statistics.pending', 1)
            ->assertJsonCount(4, 'referral_activity')
            ->assertJsonCount(1, 'reward_activity');

        $this->assertSame(AffiliateReferral::STATUS_PENDING, $pendingReferral->fresh()->status);
    }

    public function test_referral_listing_returns_only_current_affiliates_referrals(): void
    {
        $affiliate = User::factory()->create();
        $account = $this->createAffiliateAccount($affiliate, 'MINE');
        $this->createReferral($account, 'mine@example.com', AffiliateReferral::STATUS_QUALIFIED);

        $otherAffiliate = User::factory()->create();
        $otherAccount = $this->createAffiliateAccount($otherAffiliate, 'OTHER');
        $this->createReferral($otherAccount, 'other@example.com', AffiliateReferral::STATUS_QUALIFIED);

        $this->actingAs($affiliate)
            ->getJson('/api/account/affiliate/referrals')
            ->assertOk()
            ->assertJsonCount(1, 'referrals')
            ->assertJsonPath('referrals.0.referred_user.email', 'mine@example.com')
            ->assertJsonPath('statistics.qualified', 1);
    }

    public function test_reward_listing_returns_reward_statistics_and_audit_history(): void
    {
        $affiliate = User::factory()->create();
        $account = $this->createAffiliateAccount($affiliate, 'REWARDS');
        $referral = $this->createReferral($account, 'rewarded@example.com', AffiliateReferral::STATUS_QUALIFIED);
        $rewardService = app(AffiliateRewardService::class);
        $reward = $rewardService->createForQualifiedReferral($referral, [
            'reward_type' => AffiliateReward::TYPE_POINTS,
            'source' => 'dashboard_test',
            'points' => 500,
        ]);
        $rewardService->approve($reward, $affiliate);

        $this->actingAs($affiliate)
            ->getJson('/api/account/affiliate/rewards')
            ->assertOk()
            ->assertJsonCount(1, 'rewards')
            ->assertJsonPath('rewards.0.reward_type', AffiliateReward::TYPE_POINTS)
            ->assertJsonPath('rewards.0.status', AffiliateReward::STATUS_APPROVED)
            ->assertJsonPath('rewards.0.points', 500)
            ->assertJsonPath('rewards.0.referred_user.email', 'rewarded@example.com')
            ->assertJsonCount(2, 'rewards.0.audits')
            ->assertJsonPath('statistics.total', 1)
            ->assertJsonPath('statistics.approved', 1)
            ->assertJsonPath('statistics.total_points', 500)
            ->assertJsonCount(2, 'activity');
    }

    public function test_affiliate_dashboard_apis_return_empty_state_before_joining(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/account/affiliate/summary')
            ->assertOk()
            ->assertJsonPath('affiliate_account', null)
            ->assertJsonPath('referral_statistics.visits', 0)
            ->assertJsonPath('qualification_statistics.total', 0)
            ->assertJsonPath('reward_statistics.total', 0)
            ->assertJsonPath('referral_activity', [])
            ->assertJsonPath('reward_activity', []);

        $this->actingAs($user)
            ->getJson('/api/account/affiliate/referrals')
            ->assertOk()
            ->assertJsonPath('referrals', []);

        $this->actingAs($user)
            ->getJson('/api/account/affiliate/rewards')
            ->assertOk()
            ->assertJsonPath('rewards', []);
    }

    private function createAffiliateAccount(User $user, string $code): AffiliateAccount
    {
        $account = AffiliateAccount::query()->create([
            'user_id' => $user->id,
            'status' => AffiliateAccount::STATUS_ACTIVE,
            'display_name' => $user->name,
            'contact_email' => $user->email,
            'joined_at' => now(),
            'approved_at' => now(),
        ]);

        AffiliateReferralCode::query()->create([
            'affiliate_account_id' => $account->id,
            'code' => $code,
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
            'starts_at' => now()->subMinute(),
        ]);

        return $account;
    }

    private function createReferral(AffiliateAccount $account, string $email, string $status): AffiliateReferral
    {
        $code = $account->defaultReferralCode()->firstOrFail();
        $referredUser = User::factory()->create([
            'email' => $email,
        ]);
        $visit = AffiliateReferralVisit::query()->create([
            'affiliate_referral_code_id' => $code->id,
            'affiliate_account_id' => $account->id,
            'visitor_id' => 'visitor-'.$referredUser->id,
            'landing_url' => '/register?ref='.$code->code,
            'visited_at' => now(),
            'converted_user_id' => $referredUser->id,
            'converted_at' => now(),
        ]);

        return AffiliateReferral::query()->create([
            'affiliate_account_id' => $account->id,
            'affiliate_referral_code_id' => $code->id,
            'affiliate_referral_visit_id' => $visit->id,
            'referred_user_id' => $referredUser->id,
            'status' => $status,
            'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
            'attributed_at' => now(),
            'qualified_at' => $status === AffiliateReferral::STATUS_QUALIFIED ? now() : null,
            'qualified_transaction_type' => $status === AffiliateReferral::STATUS_QUALIFIED ? 'paypal' : null,
            'qualified_transaction_id' => $status === AffiliateReferral::STATUS_QUALIFIED ? 'I-dashboard-'.$referredUser->id : null,
        ]);
    }
}
