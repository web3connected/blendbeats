<?php

namespace Tests\Feature;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReward;
use App\Models\User;
use App\Services\AffiliateProgramSettings;
use App\Services\AffiliateRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateProgramSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_program_settings_reads_configured_reward_rules(): void
    {
        config([
            'affiliate.reward_plan' => 'membership_credit',
            'affiliate.qualification_event' => 'subscription_qualified',
            'affiliate.membership_credit.tier' => 'dj_pro',
            'affiliate.membership_credit.duration_days' => 45,
            'affiliate.membership_credit.expires_after_months' => 6,
            'affiliate.payouts.enabled' => true,
            'affiliate.notifications.expiring_soon_days' => 10,
        ]);

        $settings = app(AffiliateProgramSettings::class);

        $this->assertSame([
            'reward_plan' => 'membership_credit',
            'qualification_event' => 'subscription_qualified',
            'membership_credit_tier' => 'dj_pro',
            'membership_credit_days' => 45,
            'membership_credit_expiration_months' => 6,
            'expiring_soon_notification_days' => 10,
            'payouts_enabled' => true,
        ], $settings->toArray());
    }

    public function test_membership_credit_rewards_use_configured_duration_expiration_and_plan_metadata(): void
    {
        $this->travelTo(now()->setTime(11, 0));
        config([
            'affiliate.reward_plan' => 'membership_credit',
            'affiliate.qualification_event' => 'subscription_qualified',
            'affiliate.membership_credit.tier' => 'dj_pro',
            'affiliate.membership_credit.duration_days' => 45,
            'affiliate.membership_credit.expires_after_months' => 6,
        ]);

        $referral = $this->createQualifiedReferral();
        $reward = app(AffiliateRewardService::class)->createMembershipCreditForQualifiedReferral($referral);

        $this->assertNotNull($reward);
        $this->assertSame(AffiliateReward::TYPE_MEMBERSHIP_CREDIT, $reward->reward_type);
        $this->assertSame(AffiliateReward::STATUS_ISSUED, $reward->status);
        $this->assertSame(45, $reward->membership_credit_days);
        $this->assertSame(now()->addMonthsNoOverflow(6)->toDateTimeString(), $reward->expires_at->toDateTimeString());
        $this->assertSame('dj_pro', $reward->metadata['membership_tier']);
        $this->assertSame('membership_credit', $reward->metadata['reward_plan']);
        $this->assertSame('subscription_qualified', $reward->metadata['qualification_event']);
    }

    public function test_membership_credit_redemption_uses_configured_reward_plan_tier(): void
    {
        config([
            'affiliate.membership_credit.tier' => 'dj_pro',
            'affiliate.membership_credit.duration_days' => 15,
        ]);

        $referral = $this->createQualifiedReferral();
        $affiliate = $referral->affiliateAccount->user;
        $reward = app(AffiliateRewardService::class)->createMembershipCreditForQualifiedReferral($referral);

        app(AffiliateRewardService::class)->redeemMembershipCredit($reward, $affiliate);

        $affiliate->refresh();

        $this->assertSame('dj_pro', $affiliate->media_storage_tier);
        $this->assertSame('internal', $affiliate->billing_provider);
        $this->assertSame(15, $reward->fresh()->membership_credit_days);
    }

    public function test_expiring_soon_notification_window_uses_settings(): void
    {
        config([
            'affiliate.notifications.expiring_soon_days' => 3,
        ]);

        $insideWindow = app(AffiliateRewardService::class)
            ->createMembershipCreditForQualifiedReferral($this->createQualifiedReferral('inside@example.com'), [
                'expires_at' => now()->addDays(2),
            ]);
        $outsideWindow = app(AffiliateRewardService::class)
            ->createMembershipCreditForQualifiedReferral($this->createQualifiedReferral('outside@example.com'), [
                'expires_at' => now()->addDays(5),
            ]);

        $this->assertSame(1, app(AffiliateRewardService::class)->notifyExpiringMembershipCredits());
        $this->assertNotNull($insideWindow->fresh()->metadata['notifications']['membership_credit_expiring_soon_at'] ?? null);
        $this->assertNull($outsideWindow->fresh()->metadata['notifications']['membership_credit_expiring_soon_at'] ?? null);
    }

    private function createQualifiedReferral(string $email = 'settings-referral@example.com'): AffiliateReferral
    {
        $affiliate = User::factory()->create([
            'media_storage_tier' => 'free',
        ]);
        $referredUser = User::factory()->create([
            'email' => $email,
        ]);
        $account = AffiliateAccount::query()->create([
            'user_id' => $affiliate->id,
            'status' => AffiliateAccount::STATUS_ACTIVE,
            'display_name' => $affiliate->name,
            'contact_email' => $affiliate->email,
            'joined_at' => now(),
            'approved_at' => now(),
        ]);
        $code = AffiliateReferralCode::query()->create([
            'affiliate_account_id' => $account->id,
            'code' => 'SETTINGS-'.$referredUser->id,
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
            'starts_at' => now()->subMinute(),
        ]);

        return AffiliateReferral::query()->create([
            'affiliate_account_id' => $account->id,
            'affiliate_referral_code_id' => $code->id,
            'referred_user_id' => $referredUser->id,
            'status' => AffiliateReferral::STATUS_QUALIFIED,
            'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
            'attributed_at' => now(),
            'qualified_at' => now(),
            'qualified_transaction_type' => 'paypal',
            'qualified_transaction_id' => 'I-settings-'.$referredUser->id,
        ])->load('affiliateAccount.user');
    }
}
