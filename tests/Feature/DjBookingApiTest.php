<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DjBookingRequest;
use App\Models\DjProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DjBookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_booking_request_for_bookable_public_dj(): void
    {
        $profile = $this->bookableProfile();

        $this->postJson("/api/dj-hub/djs/{$profile->handle}/booking-requests", $this->bookingPayload())
            ->assertCreated()
            ->assertJsonPath('booking.status', DjBookingRequest::STATUS_PENDING)
            ->assertJsonPath('booking.event_name', 'Summer Rooftop Party')
            ->assertJsonPath('booking.dj.handle', $profile->handle);

        $this->assertDatabaseHas('dj_booking_requests', [
            'dj_profile_id' => $profile->id,
            'dj_user_id' => $profile->user_id,
            'requested_by_user_id' => null,
            'event_name' => 'Summer Rooftop Party',
            'status' => DjBookingRequest::STATUS_PENDING,
            'payment_status' => DjBookingRequest::PAYMENT_UNPAID,
        ]);
    }

    public function test_duplicate_booking_request_is_rejected(): void
    {
        $profile = $this->bookableProfile();
        $payload = $this->bookingPayload();

        $this->postJson("/api/dj-hub/djs/{$profile->handle}/booking-requests", $payload)
            ->assertCreated();

        $this->postJson("/api/dj-hub/djs/{$profile->handle}/booking-requests", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_email');

        $this->assertDatabaseCount('dj_booking_requests', 1);
    }

    public function test_booking_disabled_profile_cannot_receive_booking_requests(): void
    {
        $profile = $this->bookableProfile(['booking_enabled' => false]);

        $this->postJson("/api/dj-hub/djs/{$profile->handle}/booking-requests", $this->bookingPayload())
            ->assertNotFound();
    }

    public function test_dj_owner_can_accept_and_mark_booking_paid(): void
    {
        $profile = $this->bookableProfile();
        $booking = $this->bookingFor($profile);

        $this->actingAs($profile->user)
            ->postJson("/api/account/bookings/{$booking->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('booking.status', DjBookingRequest::STATUS_ACCEPTED);

        $this->actingAs($profile->user)
            ->postJson("/api/account/bookings/{$booking->uuid}/mark-paid")
            ->assertOk()
            ->assertJsonPath('booking.payment_status', DjBookingRequest::PAYMENT_PAID_EXTERNAL);

        $this->assertDatabaseHas('dj_booking_requests', [
            'id' => $booking->id,
            'status' => DjBookingRequest::STATUS_ACCEPTED,
            'payment_status' => DjBookingRequest::PAYMENT_PAID_EXTERNAL,
        ]);
    }

    public function test_non_owner_cannot_manage_dj_booking(): void
    {
        $profile = $this->bookableProfile();
        $booking = $this->bookingFor($profile);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->postJson("/api/account/bookings/{$booking->uuid}/accept")
            ->assertNotFound();
    }

    public function test_admin_can_view_booking_requests(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $profile = $this->bookableProfile();
        $booking = $this->bookingFor($profile);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/bookings')
            ->assertOk()
            ->assertJsonPath('bookings.0.uuid', $booking->uuid);
    }

    public function test_admin_can_view_booking_requests_page(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $profile = $this->bookableProfile();
        $this->bookingFor($profile);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/djbookings')
            ->assertOk()
            ->assertSee('DJ Bookings')
            ->assertSee('Summer Rooftop Party');
    }

    private function bookableProfile(array $overrides = []): DjProfile
    {
        $user = User::factory()->create();

        $profile = DjProfile::query()->create([
            'user_id' => $user->id,
            'dj_name' => $overrides['dj_name'] ?? 'DJ Bookable',
            'handle' => $overrides['handle'] ?? 'dj-bookable',
            'profile_headline' => 'Available for parties and clubs.',
            'bio' => 'Open format DJ.',
            'booking_enabled' => $overrides['booking_enabled'] ?? true,
            'battle_enabled' => false,
            'profile_status' => $overrides['profile_status'] ?? 'active',
            'visibility' => $overrides['visibility'] ?? 'public',
            'published_at' => now(),
        ]);

        $profile->bookingSetting()->create([
            'available_for_bookings' => true,
            'booking_email' => 'bookings@example.com',
            'show_booking_email' => false,
            'rate_type' => 'hourly',
            'minimum_rate_cents' => 15000,
            'currency' => 'USD',
            'booking_default_timezone' => 'America/New_York',
            'booking_min_notice_hours' => 24,
            'booking_max_advance_days' => 180,
            'booking_auto_accept' => false,
        ]);

        return $profile->load(['bookingSetting', 'user']);
    }

    private function bookingFor(DjProfile $profile): DjBookingRequest
    {
        return DjBookingRequest::query()->create([
            'dj_profile_id' => $profile->id,
            'dj_user_id' => $profile->user_id,
            'event_name' => 'Summer Rooftop Party',
            'event_type' => 'Private Party',
            'event_date' => now()->addDays(7)->toDateString(),
            'start_time' => '20:00',
            'end_time' => '23:00',
            'timezone' => 'America/New_York',
            'location_name' => 'Rooftop Lounge',
            'city' => 'Brooklyn',
            'state' => 'NY',
            'expected_crowd_size' => 120,
            'music_style' => 'Open Format',
            'requested_services' => ['DJ Set', 'Sound System'],
            'contact_name' => 'Event Booker',
            'contact_email' => 'booker@example.com',
            'status' => DjBookingRequest::STATUS_PENDING,
            'payment_status' => DjBookingRequest::PAYMENT_UNPAID,
        ]);
    }

    private function bookingPayload(): array
    {
        return [
            'event_name' => 'Summer Rooftop Party',
            'event_type' => 'Private Party',
            'event_date' => now()->addDays(7)->toDateString(),
            'start_time' => '20:00',
            'end_time' => '23:00',
            'timezone' => 'America/New_York',
            'location_name' => 'Rooftop Lounge',
            'location_address' => '100 Beat Street',
            'city' => 'Brooklyn',
            'state' => 'NY',
            'postal_code' => '11201',
            'country' => 'US',
            'expected_crowd_size' => 120,
            'music_style' => 'Open Format',
            'requested_services' => ['DJ Set', 'Sound System'],
            'message' => 'We need a high-energy party set.',
            'contact_name' => 'Event Booker',
            'contact_email' => 'booker@example.com',
            'contact_phone' => '555-123-4567',
        ];
    }
}
