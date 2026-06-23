<?php

namespace Tests\Feature;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralEvent;
use App\Models\AffiliateReferralVisit;
use App\Models\AffiliateReward;
use App\Models\AffiliateRewardAudit;
use App\Models\User;
use App\Services\AffiliateReferralTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateSubscriptionQualificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_paypal_subscription_approval_qualifies_pending_referral(): void
    {
        config([
            'billing.paypal.plans.dj_plus' => 'test-plan-id',
        ]);

        $this->withoutVite();

        $code = $this->createReferralCode('SUB-QUALIFY');
        $referredUser = $this->registerReferredUser($code->code, 'subscriber@example.com');

        $this->actingAs($referredUser)
            ->postJson('/api/billing/paypal/subscription-approved', [
                'subscriptionID' => 'I-qualified-subscription',
            ])
            ->assertOk()
            ->assertJsonPath('current_tier', 'dj_plus')
            ->assertJsonPath('paypal_subscription_id', 'I-qualified-subscription')
            ->assertJsonPath('referral_qualification.status', 'qualified')
            ->assertJsonPath('referral_qualification.transaction_type', 'paypal')
            ->assertJsonPath('referral_qualification.transaction_id', 'I-qualified-subscription')
            ->assertJsonPath('referral_qualification.referral_code', 'SUB-QUALIFY');

        $this->assertDatabaseHas('affiliate_referrals', [
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_referral_code_id' => $code->id,
            'referred_user_id' => $referredUser->id,
            'status' => 'qualified',
            'qualified_transaction_type' => 'paypal',
            'qualified_transaction_id' => 'I-qualified-subscription',
        ]);

        $referral = $referredUser->affiliateReferral()->firstOrFail();
        $this->assertNotNull($referral->qualified_at);

        $this->assertDatabaseHas('affiliate_referral_events', [
            'affiliate_referral_id' => $referral->id,
            'event_type' => AffiliateReferralEvent::TYPE_SUBSCRIPTION_QUALIFIED,
            'event_source' => 'paypal_subscription_approved',
            'target_type' => 'user_subscription',
            'target_id' => $referredUser->id,
            'transaction_type' => 'paypal',
            'transaction_id' => 'I-qualified-subscription',
        ]);

        $this->assertDatabaseHas('affiliate_rewards', [
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_referral_id' => $referral->id,
            'reward_type' => AffiliateReward::TYPE_MEMBERSHIP_CREDIT,
            'source' => 'subscription_qualification',
            'status' => AffiliateReward::STATUS_ISSUED,
            'membership_credit_days' => 30,
        ]);

        $reward = $referral->rewards()->firstOrFail();
        $this->assertNotNull($reward->issued_at);
        $this->assertNotNull($reward->expires_at);
        $this->assertSame('membership-credit-'.$reward->id, $reward->issued_reference);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_CREATED,
            'from_status' => null,
            'to_status' => AffiliateReward::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('affiliate_reward_audits', [
            'affiliate_reward_id' => $reward->id,
            'action' => AffiliateRewardAudit::ACTION_ISSUED,
            'from_status' => AffiliateReward::STATUS_PENDING,
            'to_status' => AffiliateReward::STATUS_ISSUED,
        ]);
    }

    public function test_paypal_active_webhook_qualifies_pending_referral(): void
    {
        $this->withoutVite();

        $code = $this->createReferralCode('WEBHOOK-QUALIFY');
        $referredUser = $this->registerReferredUser($code->code, 'webhook-subscriber@example.com');
        $referredUser->forceFill([
            'billing_provider' => 'paypal',
            'paypal_subscription_id' => 'I-webhook-subscription',
            'paypal_subscription_status' => 'approved',
        ])->save();

        $this->postJson('/api/paypal/webhook', [
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => [
                'id' => 'I-webhook-subscription',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('affiliate_referrals', [
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_referral_code_id' => $code->id,
            'referred_user_id' => $referredUser->id,
            'status' => 'qualified',
            'qualified_transaction_type' => 'paypal',
            'qualified_transaction_id' => 'I-webhook-subscription',
        ]);

        $this->assertDatabaseHas('affiliate_referral_events', [
            'event_type' => AffiliateReferralEvent::TYPE_SUBSCRIPTION_QUALIFIED,
            'event_source' => 'paypal_webhook:BILLING.SUBSCRIPTION.ACTIVATED',
            'transaction_type' => 'paypal',
            'transaction_id' => 'I-webhook-subscription',
        ]);
    }

    public function test_repeated_subscription_approval_does_not_duplicate_qualification_event(): void
    {
        config([
            'billing.paypal.plans.dj_plus' => 'test-plan-id',
        ]);

        $this->withoutVite();

        $code = $this->createReferralCode('ONCE-ONLY');
        $referredUser = $this->registerReferredUser($code->code, 'once-subscriber@example.com');

        $payload = ['subscriptionID' => 'I-once-subscription'];

        $this->actingAs($referredUser)
            ->postJson('/api/billing/paypal/subscription-approved', $payload)
            ->assertOk()
            ->assertJsonPath('referral_qualification.status', 'qualified');

        $this->actingAs($referredUser)
            ->postJson('/api/billing/paypal/subscription-approved', $payload)
            ->assertOk()
            ->assertJsonPath('referral_qualification.status', 'qualified');

        $referral = $referredUser->affiliateReferral()->firstOrFail();

        $this->assertDatabaseCount('affiliate_referrals', 1);
        $this->assertDatabaseCount('affiliate_rewards', 1);
        $this->assertSame(1, AffiliateReferralEvent::query()
            ->where('affiliate_referral_id', $referral->id)
            ->where('transaction_type', 'paypal')
            ->where('transaction_id', 'I-once-subscription')
            ->count());
        $this->assertSame(1, $referral->rewards()->count());
    }

    private function registerReferredUser(string $code, string $email): User
    {
        $this->get('/register?ref='.$code)
            ->assertOk()
            ->assertSessionHas(AffiliateReferralTrackingService::SESSION_KEY);

        $visit = AffiliateReferralVisit::query()->latest('id')->firstOrFail();

        $this->postJson('/api/auth/register', [
            'name' => 'Subscription Referral',
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
        $user = User::factory()->create();
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
            'code' => $code,
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
            'starts_at' => now()->subMinute(),
        ]);
    }
}
