<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\DjBookingRequest;

trait SerializesDjBookingRequests
{
    private function bookingPayload(DjBookingRequest $booking): array
    {
        $booking->loadMissing(['profile.user', 'requestedBy']);

        return [
            'uuid' => $booking->uuid,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'event_name' => $booking->event_name,
            'event_type' => $booking->event_type,
            'event_date' => optional($booking->event_date)->toDateString(),
            'start_time' => $this->timeValue($booking->start_time),
            'end_time' => $this->timeValue($booking->end_time),
            'timezone' => $booking->timezone,
            'location_name' => $booking->location_name,
            'location_address' => $booking->location_address,
            'city' => $booking->city,
            'state' => $booking->state,
            'postal_code' => $booking->postal_code,
            'country' => $booking->country,
            'expected_crowd_size' => $booking->expected_crowd_size,
            'music_style' => $booking->music_style,
            'requested_services' => $booking->requested_services ?? [],
            'message' => $booking->message,
            'hourly_rate_tokens' => $booking->hourly_rate_tokens,
            'hourly_rate_amount' => $booking->hourly_rate_amount === null ? null : (float) $booking->hourly_rate_amount,
            'estimated_hours' => $booking->estimated_hours === null ? null : (float) $booking->estimated_hours,
            'estimated_total_amount' => $booking->estimated_total_amount === null ? null : (float) $booking->estimated_total_amount,
            'currency' => $booking->currency,
            'contact_name' => $booking->contact_name,
            'contact_email' => $booking->contact_email,
            'contact_phone' => $booking->contact_phone,
            'internal_notes' => $booking->internal_notes,
            'dj' => [
                'id' => $booking->profile?->id,
                'name' => $booking->profile?->dj_name,
                'handle' => $booking->profile?->handle,
                'user_id' => $booking->dj_user_id,
            ],
            'requested_by' => $booking->requestedBy ? [
                'id' => $booking->requestedBy->id,
                'name' => $booking->requestedBy->name,
                'email' => $booking->requestedBy->email,
            ] : null,
            'calendar' => [
                'id' => $booking->uuid,
                'title' => $booking->event_name,
                'start' => $booking->event_date ? $booking->event_date->toDateString().'T'.$this->timeValue($booking->start_time) : null,
                'end' => $booking->event_date ? $booking->event_date->toDateString().'T'.$this->timeValue($booking->end_time) : null,
                'extendedProps' => [
                    'status' => $booking->status,
                    'paymentStatus' => $booking->payment_status,
                    'eventType' => $booking->event_type,
                    'location' => $booking->location_name,
                ],
            ],
            'accepted_at' => optional($booking->accepted_at)->toISOString(),
            'declined_at' => optional($booking->declined_at)->toISOString(),
            'cancelled_at' => optional($booking->cancelled_at)->toISOString(),
            'completed_at' => optional($booking->completed_at)->toISOString(),
            'paid_at' => optional($booking->paid_at)->toISOString(),
            'created_at' => optional($booking->created_at)->toISOString(),
            'updated_at' => optional($booking->updated_at)->toISOString(),
        ];
    }

    private function timeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return substr((string) $value, 0, 5);
    }
}
