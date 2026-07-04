<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\SerializesDjBookingRequests;
use App\Http\Controllers\Controller;
use App\Models\DjBookingRequest;
use App\Models\DjBookingSetting;
use App\Models\DjProfile;
use App\Notifications\DjBookingEventNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DjBookingRequestController extends Controller
{
    use SerializesDjBookingRequests;

    private const EVENT_TYPES = [
        'Birthday Party',
        'Wedding',
        'Club Event',
        'Private Party',
        'Corporate Event',
        'School Event',
        'Festival',
        'Other',
    ];

    private const REQUESTED_SERVICES = [
        'DJ Set',
        'MC Hosting',
        'Sound System',
        'Lighting',
        'Custom Playlist',
        'Scratch Performance',
        'Open Format Set',
    ];

    public function settings(string $handle): JsonResponse
    {
        $profile = $this->bookableProfile($handle);
        $settings = $profile->bookingSetting;

        return response()->json([
            'settings' => $this->settingsPayload($profile, $settings),
        ]);
    }

    public function store(Request $request, string $handle): JsonResponse
    {
        $profile = $this->bookableProfile($handle);
        $settings = $profile->bookingSetting;
        $attributes = $request->validate([
            'event_name' => ['required', 'string', 'max:150'],
            'event_type' => ['required', 'string', 'max:100', Rule::in(self::EVENT_TYPES)],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'timezone' => ['nullable', 'timezone:all', 'max:100'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'location_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'expected_crowd_size' => ['nullable', 'integer', 'min:1'],
            'music_style' => ['nullable', 'string', 'max:150'],
            'requested_services' => ['nullable', 'array'],
            'requested_services.*' => ['string', 'max:100', Rule::in(self::REQUESTED_SERVICES)],
            'message' => ['nullable', 'string', 'max:3000'],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'booking_website' => ['prohibited'],
        ]);

        $timezone = $attributes['timezone']
            ?? $settings?->booking_default_timezone
            ?? config('app.timezone');
        $attributes['timezone'] = $timezone;

        $this->assertBookingWindow($attributes, $settings);
        $this->assertNotDuplicate($profile, $attributes);

        [$estimatedHours, $hourlyRateAmount, $estimatedTotalAmount] = $this->estimateValue($attributes, $settings);
        $status = $settings?->booking_auto_accept ? DjBookingRequest::STATUS_ACCEPTED : DjBookingRequest::STATUS_PENDING;

        $booking = DjBookingRequest::query()->create([
            ...$attributes,
            'dj_profile_id' => $profile->id,
            'dj_user_id' => $profile->user_id,
            'requested_by_user_id' => auth('web')->id(),
            'requested_services' => $attributes['requested_services'] ?? [],
            'hourly_rate_amount' => $hourlyRateAmount,
            'estimated_hours' => $estimatedHours,
            'estimated_total_amount' => $estimatedTotalAmount,
            'currency' => $settings?->currency ?? 'USD',
            'status' => $status,
            'payment_status' => DjBookingRequest::PAYMENT_UNPAID,
            'accepted_at' => $status === DjBookingRequest::STATUS_ACCEPTED ? now() : null,
            'metadata' => [
                'source' => 'public_dj_profile',
                'request_ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ],
        ]);

        if (Schema::hasTable('notifications')) {
            $profile->user?->notify(new DjBookingEventNotification($booking->load('profile'), 'booking_created'));
        }

        return response()->json([
            'booking' => $this->bookingPayload($booking),
        ], 201);
    }

    private function bookableProfile(string $handle): DjProfile
    {
        $profile = DjProfile::query()
            ->with(['bookingSetting', 'user'])
            ->where('handle', $handle)
            ->where('visibility', 'public')
            ->where('profile_status', 'active')
            ->firstOrFail();

        if (! $profile->booking_enabled) {
            abort(404);
        }

        return $profile;
    }

    private function settingsPayload(DjProfile $profile, ?DjBookingSetting $settings): array
    {
        return [
            'dj_profile_id' => $profile->id,
            'dj_name' => $profile->dj_name,
            'handle' => $profile->handle,
            'booking_enabled' => (bool) $profile->booking_enabled,
            'available_for_bookings' => (bool) ($settings?->available_for_bookings ?? $profile->booking_enabled),
            'rate_type' => $settings?->rate_type,
            'hourly_rate_amount' => $settings?->minimum_rate_cents ? round($settings->minimum_rate_cents / 100, 2) : null,
            'currency' => $settings?->currency ?? 'USD',
            'timezone' => $settings?->booking_default_timezone ?? config('app.timezone'),
            'min_notice_hours' => (int) ($settings?->booking_min_notice_hours ?? 24),
            'max_advance_days' => (int) ($settings?->booking_max_advance_days ?? 180),
            'event_types' => self::EVENT_TYPES,
            'requested_services' => self::REQUESTED_SERVICES,
        ];
    }

    private function assertBookingWindow(array $attributes, ?DjBookingSetting $settings): void
    {
        $timezone = $attributes['timezone'];
        $eventStartsAt = Carbon::parse($attributes['event_date'].' '.$attributes['start_time'], $timezone);
        $minNoticeHours = (int) ($settings?->booking_min_notice_hours ?? 24);
        $maxAdvanceDays = (int) ($settings?->booking_max_advance_days ?? 180);

        if ($eventStartsAt->lt(now($timezone)->addHours($minNoticeHours))) {
            throw ValidationException::withMessages([
                'event_date' => ["Bookings require at least {$minNoticeHours} hours notice."],
            ]);
        }

        if ($eventStartsAt->gt(now($timezone)->addDays($maxAdvanceDays))) {
            throw ValidationException::withMessages([
                'event_date' => ["Bookings cannot be requested more than {$maxAdvanceDays} days in advance."],
            ]);
        }
    }

    private function assertNotDuplicate(DjProfile $profile, array $attributes): void
    {
        $duplicateExists = DjBookingRequest::query()
            ->where('dj_profile_id', $profile->id)
            ->where('contact_email', $attributes['contact_email'])
            ->whereDate('event_date', $attributes['event_date'])
            ->where('start_time', $attributes['start_time'])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'contact_email' => ['A similar booking request was already submitted for this DJ.'],
            ]);
        }
    }

    private function estimateValue(array $attributes, ?DjBookingSetting $settings): array
    {
        $startsAt = Carbon::parse($attributes['event_date'].' '.$attributes['start_time'], $attributes['timezone']);
        $endsAt = Carbon::parse($attributes['event_date'].' '.$attributes['end_time'], $attributes['timezone']);
        $estimatedHours = round(max(0, $startsAt->diffInMinutes($endsAt)) / 60, 2);
        $hourlyRateAmount = $settings?->minimum_rate_cents ? round($settings->minimum_rate_cents / 100, 2) : null;
        $estimatedTotalAmount = $hourlyRateAmount ? round($estimatedHours * $hourlyRateAmount, 2) : null;

        return [$estimatedHours, $hourlyRateAmount, $estimatedTotalAmount];
    }
}
