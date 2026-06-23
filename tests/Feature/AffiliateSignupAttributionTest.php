<?php

namespace Tests\Feature;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\User;
use App\Services\AffiliateReferralTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateSignupAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_referred_signup_creates_affiliate_referral_and_converts_visit(): void
    {
        $this->withoutVite();

        $code = $this->createReferralCode('ATTRIBUTION');

        $this->get('/register?ref=ATTRIBUTION')
            ->assertOk()
            ->assertSessionHas(AffiliateReferralTrackingService::SESSION_KEY);

        $visit = AffiliateReferralVisit::query()->firstOrFail();

        $this->postJson('/api/auth/register', [
            'name' => 'Attributed User',
            'email' => 'attributed-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'attributed-user@example.com')
            ->assertJsonPath('referral_context.referral_code', 'ATTRIBUTION')
            ->assertJsonPath('referral_attribution.status', 'pending')
            ->assertJsonPath('referral_attribution.attribution_type', 'signup')
            ->assertJsonPath('referral_attribution.affiliate_account_id', $code->affiliate_account_id)
            ->assertJsonPath('referral_attribution.referral_code', 'ATTRIBUTION')
            ->assertJsonPath('referral_attribution.referral_visit_id', $visit->id)
            ->assertSessionMissing(AffiliateReferralTrackingService::SESSION_KEY);

        $referredUser = User::query()->where('email', 'attributed-user@example.com')->firstOrFail();

        $this->assertDatabaseHas('affiliate_referrals', [
            'affiliate_account_id' => $code->affiliate_account_id,
            'affiliate_referral_code_id' => $code->id,
            'affiliate_referral_visit_id' => $visit->id,
            'referred_user_id' => $referredUser->id,
            'status' => 'pending',
            'attribution_type' => 'signup',
        ]);

        $visit->refresh();

        $this->assertSame($referredUser->id, $visit->converted_user_id);
        $this->assertNotNull($visit->converted_at);
    }

    public function test_signup_without_referral_context_does_not_create_affiliate_referral(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Organic User',
            'email' => 'organic-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('referral_context', null)
            ->assertJsonPath('referral_attribution', null);

        $this->assertDatabaseCount('affiliate_referrals', 0);
    }

    public function test_already_converted_visit_is_not_attributed_again(): void
    {
        $this->withoutVite();

        $this->createReferralCode('USED-VISIT');

        $this->get('/register?ref=USED-VISIT')
            ->assertOk()
            ->assertSessionHas(AffiliateReferralTrackingService::SESSION_KEY);

        $visit = AffiliateReferralVisit::query()->firstOrFail();
        $existingUser = User::factory()->create();
        $visit->forceFill([
            'converted_user_id' => $existingUser->id,
            'converted_at' => now(),
        ])->save();

        $this->postJson('/api/auth/register', [
            'name' => 'Late User',
            'email' => 'late-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('referral_context.referral_code', 'USED-VISIT')
            ->assertJsonPath('referral_attribution', null)
            ->assertSessionMissing(AffiliateReferralTrackingService::SESSION_KEY);

        $this->assertDatabaseCount('affiliate_referrals', 0);
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
