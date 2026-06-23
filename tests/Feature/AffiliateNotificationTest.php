<?php

namespace Tests\Feature;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\AffiliateReward;
use App\Models\User;
use App\Notifications\AffiliateEventNotification;
use App\Services\AffiliateReferralTrackingService;
use App\Services\AffiliateRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_account_creation_creates_notification_record(): void
    {
        $user = User::factory()->create([
            'name' => 'Affiliate Creator',
            'email' => 'affiliate-creator@example.com',
        ]);

        $this->actingAs($user)
            ->postJson('/api/account/affiliate', [
                'display_name' => 'Affiliate Creator',
                'contact_email' => 'affiliate-creator@example.com',
            ])
            ->assertCreated();

        $this->assertUserHasNotification($user, 'affiliate_account_created');
    }

    public function test_referral_signup_qualification_and_membership_credit_issue_notifications_are_recorded(): void
    {
        config(['billing.paypal.plans.dj_plus' => 'test-plan-id']);
        $this->withoutVite();

        ['user' => $affiliate, 'code' => $code] = $this->createAffiliateAccount('NOTIFY-SIGNUP');

        $referredUser = $this->registerReferredUser($code->code, 'notify-referral@example.com');

        $this->assertUserHasNotification($affiliate, 'affiliate_referral_signed_up');

        $this->actingAs($referredUser)
            ->postJson('/api/billing/paypal/subscription-approved', [
                'subscriptionID' => 'I-notification-subscription',
            ])
            ->assertOk()
            ->assertJsonPath('referral_qualification.status', 'qualified');

        $this->assertUserHasNotification($affiliate, 'affiliate_referral_qualified');
        $this->assertUserHasNotification($affiliate, 'affiliate_membership_credit_issued');
    }

    public function test_membership_credit_redemption_creates_notification_record(): void
    {
        ['user' => $affiliate, 'account' => $account] = $this->createAffiliateAccount('NOTIFY-REDEEM');
        $reward = $this->createMembershipCreditReward($account, 'notify-redeem@example.com');

        $this->actingAs($affiliate)
            ->postJson("/api/account/affiliate/rewards/{$reward->id}/redeem")
            ->assertOk();

        $this->assertUserHasNotification($affiliate, 'affiliate_membership_credit_redeemed');
    }

    public function test_expiring_and_expired_membership_credit_notifications_are_recorded_once(): void
    {
        $this->travelTo(now()->setTime(10, 0));

        ['user' => $affiliate, 'account' => $account] = $this->createAffiliateAccount('NOTIFY-EXPIRE');
        $expiringReward = $this->createMembershipCreditReward($account, 'notify-expiring@example.com', [
            'expires_at' => now()->addDays(3),
        ]);
        $expiredReward = $this->createMembershipCreditReward($account, 'notify-expired@example.com', [
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('affiliate:expire-membership-credits')
            ->expectsOutput('Affiliate membership credits synced. Expiring soon: 1. Expired: 1.')
            ->assertExitCode(0);

        $this->assertSame(AffiliateReward::STATUS_ISSUED, $expiringReward->fresh()->status);
        $this->assertSame(AffiliateReward::STATUS_EXPIRED, $expiredReward->fresh()->status);
        $this->assertUserHasNotification($affiliate, 'affiliate_membership_credit_expiring_soon');
        $this->assertUserHasNotification($affiliate, 'affiliate_membership_credit_expired');

        $this->artisan('affiliate:expire-membership-credits')
            ->expectsOutput('Affiliate membership credits synced. Expiring soon: 0. Expired: 0.')
            ->assertExitCode(0);

        $this->assertSame(1, $this->notificationCount($affiliate, 'affiliate_membership_credit_expiring_soon'));
        $this->assertSame(1, $this->notificationCount($affiliate, 'affiliate_membership_credit_expired'));
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
            'qualified_transaction_id' => 'I-notification-'.$referredUser->id,
        ]);
    }

    private function registerReferredUser(string $code, string $email): User
    {
        $this->get('/register?ref='.$code)
            ->assertOk()
            ->assertSessionHas(AffiliateReferralTrackingService::SESSION_KEY);

        $visit = AffiliateReferralVisit::query()->latest('id')->firstOrFail();

        $this->postJson('/api/auth/register', [
            'name' => 'Notification Referral',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('referral_context.referral_visit_id', $visit->id)
            ->assertJsonPath('referral_attribution.status', 'pending');

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function createAffiliateAccount(string $code): array
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

    private function assertUserHasNotification(User $user, string $eventType): void
    {
        $this->assertGreaterThan(
            0,
            $this->notificationCount($user, $eventType),
            "Failed asserting that user {$user->id} has notification {$eventType}.",
        );
    }

    private function notificationCount(User $user, string $eventType): int
    {
        return $user->notifications()
            ->where('type', AffiliateEventNotification::class)
            ->get()
            ->filter(fn ($notification): bool => ($notification->data['event_type'] ?? null) === $eventType)
            ->count();
    }
}
