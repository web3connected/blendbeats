<?php

namespace App\Notifications;

use App\Models\DjBookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DjBookingEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DjBookingRequest $booking,
        private readonly string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $djName = $this->booking->profile?->dj_name ?? 'DJ';

        [$title, $message, $actionLabel, $actionUrl] = match ($this->event) {
            'booking_created' => [
                'New booking request',
                "{$this->booking->contact_name} requested {$djName} for {$this->booking->event_name}.",
                'Review Booking',
                "/account/bookings/{$this->booking->uuid}",
            ],
            'booking_accepted' => [
                'Booking accepted',
                "{$djName} accepted the booking request for {$this->booking->event_name}.",
                'View Booking',
                "/account/bookings/{$this->booking->uuid}",
            ],
            'booking_declined' => [
                'Booking declined',
                "{$djName} declined the booking request for {$this->booking->event_name}.",
                'View Bookings',
                '/account/bookings',
            ],
            'booking_needs_discussion' => [
                'Booking needs discussion',
                "{$djName} marked {$this->booking->event_name} as needing discussion.",
                'View Booking',
                "/account/bookings/{$this->booking->uuid}",
            ],
            'booking_cancelled' => [
                'Booking cancelled',
                "The booking for {$this->booking->event_name} was cancelled.",
                'View Bookings',
                '/account/bookings',
            ],
            'booking_completed' => [
                'Booking completed',
                "{$this->booking->event_name} was marked completed.",
                'View Booking',
                "/account/bookings/{$this->booking->uuid}",
            ],
            default => [
                'Booking updated',
                "{$this->booking->event_name} has a booking update.",
                'View Booking',
                "/account/bookings/{$this->booking->uuid}",
            ],
        };

        return [
            'title' => $title,
            'message' => $message,
            'category' => 'bookings',
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'icon' => 'calendar-check',
            'booking_uuid' => $this->booking->uuid,
            'booking_status' => $this->booking->status,
            'booking_payment_status' => $this->booking->payment_status,
            'dj_profile_id' => $this->booking->dj_profile_id,
            'event_date' => optional($this->booking->event_date)->toDateString(),
        ];
    }
}
