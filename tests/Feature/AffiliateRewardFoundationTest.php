<?php

namespace Tests\Feature;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReward;
use App\Models\AffiliateRewardAudit;
use App\Models\User;
use App\Services\AffiliateRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateRewardFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reward_service_creates_pending_reward_for_qualified_referral(): void
    {
        $referral = $this->createReferral(AffiliateReferral::STATUS_QUALIFIED);

        $reward = app(AffiliateRewardService::class)->createForQualifiedReferral($referral);

        $this->assertNotNull($reward);
        $this->assertSame($referral->affiliate_account_id, $reward->affiliate_account_id);
        $this->assertSame($referral->id, $reward->affiliate_referral_id);
        $this->assertSame(AffiliateReward::TYPE_FUTURE_INCENTIVE, $reward->reward_type);
        $this->assertSame('subscription_qualification', $reward->source);
        $this->assertSame(AffiliateReward::STATUS_PENDING, $reward->status);
        $this->assertNotNull($reward->available_at);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_CREATED,
            'from_status' => null,
            'to_status' => AffiliateReward::STATUS_PENDING,
        ]);
    }

    public function test_reward_service_does_not_create_reward_for_unqualified_referral(): void
    {
        $referral = $this->createReferral(AffiliateReferral::STATUS_PENDING);

        $reward = app(AffiliateRewardService::class)->createForQualifiedReferral($referral);

        $this->assertNull($reward);
        $this->assertDatabaseCount('affiliate_rewards', 0);
        $this->assertDatabaseCount('affiliate_reward_audits', 0);
    }

    public function test_reward_service_supports_different_reward_types(): void
    {
        $referral = $this->createReferral(AffiliateReferral::STATUS_QUALIFIED);

        $reward = app(AffiliateRewardService::class)->createForQualifiedReferral($referral, [
            'reward_type' => AffiliateReward::TYPE_AD_CREDIT,
            'source' => 'manual_ad_credit_bonus',
            'amount_cents' => 2500,
            'points' => 100,
            'quantity' => 2,
            'metadata' => [
                'label' => 'Affiliate launch bonus',
            ],
        ]);

        $this->assertNotNull($reward);
        $this->assertSame(AffiliateReward::TYPE_AD_CREDIT, $reward->reward_type);
        $this->assertSame('manual_ad_credit_bonus', $reward->source);
        $this->assertSame(2500, $reward->amount_cents);
        $this->assertSame(100, $reward->points);
        $this->assertSame(2, $reward->quantity);
        $this->assertSame('Affiliate launch bonus', $reward->metadata['label']);
    }

    public function test_reward_status_transitions_track_approval_issuance_payment_and_audit_history(): void
    {
        $actor = User::factory()->create();
        $referral = $this->createReferral(AffiliateReferral::STATUS_QUALIFIED);
        $service = app(AffiliateRewardService::class);
        $reward = $service->createForQualifiedReferral($referral);

        $approved = $service->approve($reward, $actor, ['note' => 'Ready for issue']);
        $this->assertSame(AffiliateReward::STATUS_APPROVED, $approved->status);
        $this->assertNotNull($approved->approved_at);

        $issued = $service->issue($approved, 'AD-CREDIT-123', $actor);
        $this->assertSame(AffiliateReward::STATUS_ISSUED, $issued->status);
        $this->assertSame('AD-CREDIT-123', $issued->issued_reference);
        $this->assertNotNull($issued->issued_at);

        $paid = $service->markPaid($issued, $actor);
        $this->assertSame(AffiliateReward::STATUS_PAID, $paid->status);
        $this->assertNotNull($paid->paid_at);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_APPROVED,
            'from_status' => AffiliateReward::STATUS_PENDING,
            'to_status' => AffiliateReward::STATUS_APPROVED,
            'actor_type' => User::class,
            'actor_id' => $actor->id,
        ]);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_ISSUED,
            'from_status' => AffiliateReward::STATUS_APPROVED,
            'to_status' => AffiliateReward::STATUS_ISSUED,
        ]);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_PAID,
            'from_status' => AffiliateReward::STATUS_ISSUED,
            'to_status' => AffiliateReward::STATUS_PAID,
        ]);

        $this->assertSame(4, AffiliateRewardAudit::query()->where('affiliate_reward_id', $reward->id)->count());
    }

    public function test_reward_creation_is_idempotent_per_referral_type_and_source(): void
    {
        $referral = $this->createReferral(AffiliateReferral::STATUS_QUALIFIED);
        $service = app(AffiliateRewardService::class);

        $firstReward = $service->createForQualifiedReferral($referral);
        $secondReward = $service->createForQualifiedReferral($referral);

        $this->assertSame($firstReward->id, $secondReward->id);
        $this->assertDatabaseCount('affiliate_rewards', 1);
        $this->assertDatabaseCount('affiliate_reward_audits', 1);
    }

    private function createReferral(string $status): AffiliateReferral
    {
        $affiliateUser = User::factory()->create();
        $referredUser = User::factory()->create();
        $account = AffiliateAccount::query()->create([
            'user_id' => $affiliateUser->id,
            'status' => AffiliateAccount::STATUS_ACTIVE,
            'display_name' => $affiliateUser->name,
            'contact_email' => $affiliateUser->email,
            'joined_at' => now(),
            'approved_at' => now(),
        ]);
        $code = AffiliateReferralCode::query()->create([
            'affiliate_account_id' => $account->id,
            'code' => 'REWARD-'.$referredUser->id,
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
            'starts_at' => now()->subMinute(),
        ]);

        return AffiliateReferral::query()->create([
            'affiliate_account_id' => $account->id,
            'affiliate_referral_code_id' => $code->id,
            'referred_user_id' => $referredUser->id,
            'status' => $status,
            'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
            'attributed_at' => now(),
            'qualified_at' => $status === AffiliateReferral::STATUS_QUALIFIED ? now() : null,
            'qualified_transaction_type' => $status === AffiliateReferral::STATUS_QUALIFIED ? 'paypal' : null,
            'qualified_transaction_id' => $status === AffiliateReferral::STATUS_QUALIFIED ? 'I-reward-test' : null,
        ]);
    }
}
