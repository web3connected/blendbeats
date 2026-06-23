<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\User;
use App\Services\AffiliateFraudProtectionService;
use App\Services\AffiliateReferralAttributionService;
use App\Services\AffiliateReferralTrackingService;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliateFraudProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_referral_is_rejected_with_fraud_reason(): void
    {
        $affiliateUser = User::factory()->create([
            'email' => 'self-affiliate@example.com',
        ]);
        $code = $this->createReferralCode('SELF-CHECK', $affiliateUser);
        $visit = $this->createVisit($code, '10.1.1.1', 'Self Agent');

        $referral = app(AffiliateReferralAttributionService::class)
            ->attributeSignup($affiliateUser, $this->contextFor($code, $visit), $this->requestFor('10.1.1.1', 'Self Agent'));

        $this->assertSame(AffiliateReferral::STATUS_REJECTED, $referral?->status);
        $this->assertSame(AffiliateFraudProtectionService::REASON_SELF_REFERRAL, $referral?->rejection_reason);
        $this->assertTrue($referral?->is_suspicious);
        $this->assertSame(AffiliateFraudProtectionService::REASON_SELF_REFERRAL, $referral?->fraud_reason);
        $this->assertContains(AffiliateFraudProtectionService::REASON_SELF_REFERRAL, $referral?->fraud_flags ?? []);
    }

    public function test_duplicate_attribution_attempt_is_blocked_and_tracked(): void
    {
        $user = User::factory()->create([
            'email' => 'duplicate-attribution@example.com',
        ]);
        $firstCode = $this->createReferralCode('FIRST-CODE');
        $secondCode = $this->createReferralCode('SECOND-CODE');
        $firstVisit = $this->createVisit($firstCode, '10.2.2.1', 'Duplicate Agent');
        $secondVisit = $this->createVisit($secondCode, '10.2.2.1', 'Duplicate Agent');
        $attribution = app(AffiliateReferralAttributionService::class);

        $firstReferral = $attribution->attributeSignup(
            $user,
            $this->contextFor($firstCode, $firstVisit),
            $this->requestFor('10.2.2.1', 'Duplicate Agent'),
        );

        $secondReferral = $attribution->attributeSignup(
            $user,
            $this->contextFor($secondCode, $secondVisit),
            $this->requestFor('10.2.2.1', 'Duplicate Agent'),
        );

        $this->assertSame($firstReferral?->id, $secondReferral?->id);
        $this->assertDatabaseCount('affiliate_referrals', 1);

        $firstReferral->refresh();

        $this->assertTrue($firstReferral->is_suspicious);
        $this->assertSame(AffiliateFraudProtectionService::REASON_DUPLICATE_ATTRIBUTION, $firstReferral->fraud_reason);
        $this->assertContains(AffiliateFraudProtectionService::REASON_DUPLICATE_ATTRIBUTION, $firstReferral->fraud_flags ?? []);
        $this->assertSame(1, $firstReferral->metadata['duplicate_attribution_attempts'] ?? null);
    }

    public function test_repeated_referral_visits_are_marked_suspicious(): void
    {
        $this->withoutVite();

        $code = $this->createReferralCode('REPEAT-VISIT');

        foreach (range(1, 4) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.3.3.3'])
                ->withHeader('User-Agent', 'Repeated Visit Agent')
                ->get('/?ref='.$code->code.'&attempt='.$attempt)
                ->assertOk();
        }

        $visit = AffiliateReferralVisit::query()->latest('id')->firstOrFail();

        $this->assertTrue($visit->is_suspicious);
        $this->assertSame(AffiliateFraudProtectionService::REASON_REPEATED_VISITOR, $visit->suspicious_reason);
        $this->assertContains(AffiliateFraudProtectionService::REASON_REPEATED_VISITOR, $visit->metadata['fraud_flags'] ?? []);
    }

    public function test_suspicious_signup_is_tracked_and_blocked_from_automatic_reward_qualification(): void
    {
        config([
            'billing.paypal.plans.dj_plus' => 'test-plan-id',
        ]);

        $this->withoutVite();

        $code = $this->createReferralCode('MISMATCH');

        $this->withServerVariables(['REMOTE_ADDR' => '10.4.4.1'])
            ->withHeader('User-Agent', 'Original Visit Agent')
            ->get('/register?ref=MISMATCH')
            ->assertOk()
            ->assertSessionHas(AffiliateReferralTrackingService::SESSION_KEY);

        $this->withServerVariables(['REMOTE_ADDR' => '10.4.4.2'])
            ->withHeader('User-Agent', 'Signup Device Agent')
            ->postJson('/api/auth/register', [
                'name' => 'Suspicious Signup',
                'email' => 'suspicious-signup@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('referral_attribution.is_suspicious', true)
            ->assertJsonPath('referral_attribution.fraud_reason', AffiliateFraudProtectionService::REASON_SIGNUP_DEVICE_MISMATCH);

        $referredUser = User::query()->where('email', 'suspicious-signup@example.com')->firstOrFail();
        $referral = $referredUser->affiliateReferral()->firstOrFail();

        $this->assertTrue($referral->is_suspicious);
        $this->assertContains('ip_hash_mismatch', $referral->fraud_flags ?? []);
        $this->assertContains('user_agent_hash_mismatch', $referral->fraud_flags ?? []);

        $this->actingAs($referredUser)
            ->postJson('/api/billing/paypal/subscription-approved', [
                'subscriptionID' => 'I-fraud-blocked',
            ])
            ->assertOk()
            ->assertJsonPath('referral_qualification', null);

        $referral->refresh();

        $this->assertSame(AffiliateReferral::STATUS_REJECTED, $referral->status);
        $this->assertSame(AffiliateFraudProtectionService::REASON_SIGNUP_DEVICE_MISMATCH, $referral->rejection_reason);
        $this->assertContains('qualification_blocked', $referral->fraud_flags ?? []);
        $this->assertDatabaseCount('affiliate_rewards', 0);
    }

    public function test_admin_can_see_and_reject_fraud_review_referrals(): void
    {
        $admin = $this->superAdmin();
        $code = $this->createReferralCode('ADMIN-FRAUD');
        $referredUser = User::factory()->create([
            'name' => 'Fraud Review Listener',
            'email' => 'fraud-review@example.com',
        ]);
        $visit = AffiliateReferralVisit::query()->create([
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_referral_code_id' => $code->id,
            'visitor_id' => 'admin-fraud-visitor',
            'landing_url' => '/register?ref='.$code->code,
            'visited_at' => now(),
            'is_suspicious' => true,
            'suspicious_reason' => AffiliateFraudProtectionService::REASON_REPEATED_IP_USER_AGENT,
            'suspicious_at' => now(),
        ]);
        $referral = AffiliateReferral::query()->create([
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_referral_code_id' => $code->id,
            'affiliate_referral_visit_id' => $visit->id,
            'referred_user_id' => $referredUser->id,
            'status' => AffiliateReferral::STATUS_PENDING,
            'attribution_type' => AffiliateReferral::ATTRIBUTION_SIGNUP,
            'attributed_at' => now(),
            'is_suspicious' => true,
            'fraud_reason' => AffiliateFraudProtectionService::REASON_SUSPICIOUS_VISIT,
            'fraud_flags' => [
                AffiliateFraudProtectionService::REASON_SUSPICIOUS_VISIT,
                AffiliateFraudProtectionService::REASON_REPEATED_IP_USER_AGENT,
            ],
            'fraud_checked_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/affiliatereferrals?search=suspicious_visit')
            ->assertOk()
            ->assertSee('Fraud Review')
            ->assertSee('Suspicious Visit')
            ->assertSee('Repeated Ip User Agent');

        $this->actingAs($admin, 'admin')
            ->patch("/admin/admincenter/affiliatereferrals/{$referral->id}/status", [
                'status' => AffiliateReferral::STATUS_REJECTED,
                'rejection_reason' => 'manual_fraud_review',
            ])
            ->assertRedirect(route('admin.admincenter.affiliatereferrals.index', [
                'status' => AffiliateReferral::STATUS_REJECTED,
            ]));

        $referral->refresh();

        $this->assertSame(AffiliateReferral::STATUS_REJECTED, $referral->status);
        $this->assertTrue($referral->is_suspicious);
        $this->assertSame('manual_fraud_review', $referral->rejection_reason);
        $this->assertSame('manual_fraud_review', $referral->fraud_reason);
        $this->assertContains('admin_rejected', $referral->fraud_flags ?? []);
    }

    private function createReferralCode(string $code, ?User $affiliateUser = null): AffiliateReferralCode
    {
        $user = $affiliateUser ?: User::factory()->create([
            'email' => str($code)->slug()->append('@example.com')->toString(),
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
            'code' => $code,
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
            'starts_at' => now()->subMinute(),
        ]);
    }

    private function createVisit(AffiliateReferralCode $code, string $ip, string $userAgent): AffiliateReferralVisit
    {
        $fraud = app(AffiliateFraudProtectionService::class);

        return AffiliateReferralVisit::query()->create([
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_referral_code_id' => $code->id,
            'visitor_id' => 'manual-visitor-'.str()->random(8),
            'landing_url' => '/register?ref='.$code->code,
            'ip_hash' => $fraud->hashValue($ip),
            'user_agent_hash' => $fraud->hashValue($userAgent),
            'visited_at' => now(),
        ]);
    }

    private function contextFor(AffiliateReferralCode $code, AffiliateReferralVisit $visit): array
    {
        return [
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_referral_code_id' => $code->id,
            'referral_visit_id' => $visit->id,
            'referral_code' => $code->code,
            'captured_at' => now()->toISOString(),
            'expires_at' => now()->addDays(AffiliateReferralTrackingService::ATTRIBUTION_WINDOW_DAYS)->toISOString(),
        ];
    }

    private function requestFor(string $ip, string $userAgent): Request
    {
        return Request::create('/api/auth/register', 'POST', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $userAgent,
        ]);
    }

    private function superAdmin(): Admin
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Fraud Admin',
            'email' => 'fraud-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        return $admin;
    }
}
