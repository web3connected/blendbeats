<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_affiliate_account(): void
    {
        $this->postJson('/api/account/affiliate')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_affiliate_account(): void
    {
        $user = User::factory()->create([
            'name' => 'DJ Affiliate',
            'email' => 'affiliate@example.com',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/account/affiliate', [
                'display_name' => 'DJ Affiliate Brand',
                'contact_email' => 'bookings@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('affiliate_account.status', 'active')
            ->assertJsonPath('affiliate_account.display_name', 'DJ Affiliate Brand')
            ->assertJsonPath('affiliate_account.contact_email', 'bookings@example.com')
            ->assertJsonPath('outputs.affiliate_account_created', true)
            ->assertJsonPath('outputs.affiliate_status_assigned', 'active')
            ->assertJsonPath('outputs.affiliate_profile_established', true);

        $code = $response->json('affiliate_account.referral_code');
        $link = $response->json('affiliate_account.referral_link');

        $this->assertNotEmpty($code);
        $this->assertStringEndsWith('/register?ref='.rawurlencode($code), $link);

        $this->assertDatabaseHas('affiliate_accounts', [
            'user_id' => $user->id,
            'status' => 'active',
            'display_name' => 'DJ Affiliate Brand',
            'contact_email' => 'bookings@example.com',
        ]);

        $this->assertDatabaseHas('affiliate_referral_codes', [
            'code' => $code,
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_affiliate_registration_defaults_profile_from_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Default Affiliate',
            'email' => 'default-affiliate@example.com',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/account/affiliate')
            ->assertCreated()
            ->assertJsonPath('affiliate_account.display_name', 'Default Affiliate')
            ->assertJsonPath('affiliate_account.contact_email', 'default-affiliate@example.com');

        $this->assertStringStartsWith('DEFAULT-AFFILIATE-', $response->json('affiliate_account.referral_code'));
    }

    public function test_affiliate_registration_is_idempotent_for_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Repeat Affiliate',
            'email' => 'repeat-affiliate@example.com',
        ]);

        $this->actingAs($user)
            ->postJson('/api/account/affiliate', [
                'display_name' => 'Repeat Affiliate',
                'contact_email' => 'repeat-affiliate@example.com',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/api/account/affiliate', [
                'display_name' => 'Changed Name',
                'contact_email' => 'changed@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('affiliate_account.display_name', 'Repeat Affiliate')
            ->assertJsonPath('affiliate_account.contact_email', 'repeat-affiliate@example.com')
            ->assertJsonPath('outputs.affiliate_account_created', false);

        $this->assertDatabaseCount('affiliate_accounts', 1);
        $this->assertDatabaseCount('affiliate_referral_codes', 1);
    }

    public function test_user_can_read_current_affiliate_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Reader Affiliate',
            'email' => 'reader-affiliate@example.com',
        ]);

        $this->actingAs($user)
            ->getJson('/api/account/affiliate')
            ->assertOk()
            ->assertJsonPath('affiliate_account', null);

        $this->actingAs($user)
            ->postJson('/api/account/affiliate')
            ->assertCreated();

        $response = $this->actingAs($user)
            ->getJson('/api/account/affiliate')
            ->assertOk()
            ->assertJsonPath('affiliate_account.status', 'active')
            ->assertJsonPath('affiliate_account.display_name', 'Reader Affiliate');

        $this->assertStringStartsWith('READER-AFFILIATE-', $response->json('affiliate_account.referral_code'));
    }

    public function test_each_affiliate_receives_unique_referral_code_and_link(): void
    {
        $firstUser = User::factory()->create([
            'name' => 'Same Name',
            'email' => 'same-one@example.com',
        ]);
        $secondUser = User::factory()->create([
            'name' => 'Same Name',
            'email' => 'same-two@example.com',
        ]);

        $firstResponse = $this->actingAs($firstUser)
            ->postJson('/api/account/affiliate')
            ->assertCreated();

        $secondResponse = $this->actingAs($secondUser)
            ->postJson('/api/account/affiliate')
            ->assertCreated();

        $firstCode = $firstResponse->json('affiliate_account.referral_code');
        $secondCode = $secondResponse->json('affiliate_account.referral_code');

        $this->assertNotSame($firstCode, $secondCode);
        $this->assertStringEndsWith('/register?ref='.rawurlencode($firstCode), $firstResponse->json('affiliate_account.referral_link'));
        $this->assertStringEndsWith('/register?ref='.rawurlencode($secondCode), $secondResponse->json('affiliate_account.referral_link'));

        $this->assertDatabaseCount('affiliate_referral_codes', 2);
    }
}
