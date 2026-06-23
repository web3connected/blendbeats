<?php

namespace Tests\Feature;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferralCode;
use App\Models\AffiliateReferralVisit;
use App\Models\User;
use App\Services\AffiliateReferralTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateReferralTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_link_visit_captures_code_records_visit_and_stores_context(): void
    {
        $this->withoutVite();

        $code = $this->createReferralCode('TRACK-ME');

        $this->withHeader('referer', 'https://example.com/source')
            ->get('/?ref=track-me&utm_source=newsletter&utm_medium=email&utm_campaign=launch')
            ->assertOk()
            ->assertSessionHas(AffiliateReferralTrackingService::SESSION_KEY)
            ->assertCookie(AffiliateReferralTrackingService::COOKIE_NAME);

        $visit = AffiliateReferralVisit::query()->firstOrFail();

        $this->assertSame($code->id, $visit->affiliate_referral_code_id);
        $this->assertSame($code->affiliate_account_id, $visit->affiliate_account_id);
        $this->assertSame('https://example.com/source', $visit->referrer_url);
        $this->assertSame('newsletter', $visit->utm_source);
        $this->assertSame('email', $visit->utm_medium);
        $this->assertSame('launch', $visit->utm_campaign);
        $this->assertNotNull($visit->visitor_id);
        $this->assertNotNull($visit->visited_at);

        $context = session(AffiliateReferralTrackingService::SESSION_KEY);

        $this->assertSame($code->id, $context['affiliate_referral_code_id']);
        $this->assertSame($visit->id, $context['referral_visit_id']);
        $this->assertSame('TRACK-ME', $context['referral_code']);
        $this->assertArrayHasKey('expires_at', $context);
    }

    public function test_invalid_referral_code_is_not_recorded_or_stored(): void
    {
        $this->withoutVite();

        $this->get('/?ref=missing-code')
            ->assertOk()
            ->assertSessionMissing(AffiliateReferralTrackingService::SESSION_KEY)
            ->assertCookieMissing(AffiliateReferralTrackingService::COOKIE_NAME);

        $this->assertDatabaseCount('affiliate_referral_visits', 0);
    }

    public function test_disabled_referral_code_is_not_recorded_or_stored(): void
    {
        $this->withoutVite();

        $this->createReferralCode('DISABLED', AffiliateReferralCode::STATUS_DISABLED);

        $this->get('/?ref=DISABLED')
            ->assertOk()
            ->assertSessionMissing(AffiliateReferralTrackingService::SESSION_KEY)
            ->assertCookieMissing(AffiliateReferralTrackingService::COOKIE_NAME);

        $this->assertDatabaseCount('affiliate_referral_visits', 0);
    }

    public function test_referral_context_is_available_during_registration(): void
    {
        $this->withoutVite();

        $code = $this->createReferralCode('JOIN-WITH-ME');

        $this->get('/register?ref=JOIN-WITH-ME')
            ->assertOk()
            ->assertSessionHas(AffiliateReferralTrackingService::SESSION_KEY);

        $visit = AffiliateReferralVisit::query()->firstOrFail();

        $this->postJson('/api/auth/register', [
            'name' => 'Referred User',
            'email' => 'referred-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'referred-user@example.com')
            ->assertJsonPath('referral_context.referral_code', $code->code)
            ->assertJsonPath('referral_context.referral_visit_id', $visit->id)
            ->assertJsonPath('referral_attribution.referral_code', $code->code);
    }

    private function createReferralCode(
        string $code,
        string $status = AffiliateReferralCode::STATUS_ACTIVE,
    ): AffiliateReferralCode {
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
            'status' => $status,
            'is_default' => true,
            'starts_at' => now()->subMinute(),
        ]);
    }
}
