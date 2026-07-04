<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\SerializesDjBookingRequests;
use App\Http\Controllers\Controller;
use App\Models\DjBookingRequest;
use App\Notifications\DjBookingEventNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountDjBookingController extends Controller
{
    use SerializesDjBookingRequests;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in($this->bookingStatuses())],
            'payment_status' => ['nullable', 'string', Rule::in($this->paymentStatuses())],
            'include_cancelled' => ['nullable', 'boolean'],
        ]);

        $baseQuery = DjBookingRequest::query()
            ->with(['profile', 'requestedBy'])
            ->forDjUser($request->user()->id);

        $bookings = (clone $baseQuery)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn ($query, string $status) => $query->where('payment_status', $status))
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->latest('created_at')
            ->limit(100)
            ->get();

        $calendarQuery = (clone $baseQuery)->calendarVisible();

        if (! $request->boolean('include_cancelled')) {
            $calendarQuery->whereNotIn('status', [
                DjBookingRequest::STATUS_CANCELLED,
                DjBookingRequest::STATUS_DECLINED,
            ]);
        }

        $calendarBookings = $calendarQuery
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(250)
            ->get();

        return response()->json([
            'bookings' => $bookings->map(fn (DjBookingRequest $booking): array => $this->bookingPayload($booking))->values(),
            'calendar_events' => $calendarBookings->map(fn (DjBookingRequest $booking): array => $this->bookingPayload($booking)['calendar'])->values(),
            'analytics' => $this->analytics($baseQuery),
            'filters' => [
                'statuses' => $this->bookingStatuses(),
                'payment_statuses' => $this->paymentStatuses(),
            ],
        ]);
    }

    public function show(Request $request, string $booking): JsonResponse
    {
        return response()->json([
            'booking' => $this->bookingPayload($this->bookingForDj($request, $booking)),
        ]);
    }

    public function accept(Request $request, string $booking): JsonResponse
    {
        $bookingRequest = $this->bookingForDj($request, $booking);
        $this->assertStatus($bookingRequest, [
            DjBookingRequest::STATUS_PENDING,
            DjBookingRequest::STATUS_NEEDS_DISCUSSION,
        ], 'Only pending bookings can be accepted.');

        return $this->changeStatus($request, $bookingRequest, DjBookingRequest::STATUS_ACCEPTED, 'booking_accepted', [
            'accepted_at' => now(),
        ]);
    }

    public function decline(Request $request, string $booking): JsonResponse
    {
        $bookingRequest = $this->bookingForDj($request, $booking);
        $this->assertStatus($bookingRequest, [
            DjBookingRequest::STATUS_PENDING,
            DjBookingRequest::STATUS_NEEDS_DISCUSSION,
        ], 'Only pending bookings can be declined.');

        return $this->changeStatus($request, $bookingRequest, DjBookingRequest::STATUS_DECLINED, 'booking_declined', [
            'declined_at' => now(),
        ]);
    }

    public function needsDiscussion(Request $request, string $booking): JsonResponse
    {
        $bookingRequest = $this->bookingForDj($request, $booking);
        $this->assertStatus($bookingRequest, [
            DjBookingRequest::STATUS_PENDING,
            DjBookingRequest::STATUS_ACCEPTED,
        ], 'This booking cannot be marked as needing discussion.');

        return $this->changeStatus($request, $bookingRequest, DjBookingRequest::STATUS_NEEDS_DISCUSSION, 'booking_needs_discussion');
    }

    public function cancel(Request $request, string $booking): JsonResponse
    {
        $bookingRequest = $this->bookingForDj($request, $booking);
        $this->assertStatus($bookingRequest, [
            DjBookingRequest::STATUS_PENDING,
            DjBookingRequest::STATUS_NEEDS_DISCUSSION,
            DjBookingRequest::STATUS_ACCEPTED,
        ], 'This booking cannot be cancelled.');

        return $this->changeStatus($request, $bookingRequest, DjBookingRequest::STATUS_CANCELLED, 'booking_cancelled', [
            'cancelled_at' => now(),
        ]);
    }

    public function complete(Request $request, string $booking): JsonResponse
    {
        $bookingRequest = $this->bookingForDj($request, $booking);
        $this->assertStatus($bookingRequest, [
            DjBookingRequest::STATUS_ACCEPTED,
        ], 'Only accepted bookings can be completed.');

        return $this->changeStatus($request, $bookingRequest, DjBookingRequest::STATUS_COMPLETED, 'booking_completed', [
            'completed_at' => now(),
        ]);
    }

    public function markPaid(Request $request, string $booking): JsonResponse
    {
        $attributes = $request->validate([
            'payment_status' => ['nullable', 'string', Rule::in($this->paymentStatuses())],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $bookingRequest = $this->bookingForDj($request, $booking);

        if (in_array($bookingRequest->status, [
            DjBookingRequest::STATUS_DECLINED,
            DjBookingRequest::STATUS_CANCELLED,
            DjBookingRequest::STATUS_EXPIRED,
        ], true)) {
            throw ValidationException::withMessages([
                'booking' => ['Payment status cannot be changed for this booking.'],
            ]);
        }

        $paymentStatus = $attributes['payment_status'] ?? DjBookingRequest::PAYMENT_PAID_EXTERNAL;

        $bookingRequest->forceFill([
            'payment_status' => $paymentStatus,
            'paid_at' => $paymentStatus === DjBookingRequest::PAYMENT_PAID_EXTERNAL ? ($bookingRequest->paid_at ?? now()) : null,
            'internal_notes' => $attributes['internal_notes'] ?? $bookingRequest->internal_notes,
        ])->save();

        return response()->json([
            'booking' => $this->bookingPayload($bookingRequest->refresh()),
        ]);
    }

    private function changeStatus(Request $request, DjBookingRequest $booking, string $status, string $notificationEvent, array $extra = []): JsonResponse
    {
        $attributes = $request->validate([
            'internal_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $booking->forceFill([
            'status' => $status,
            'internal_notes' => $attributes['internal_notes'] ?? $booking->internal_notes,
            ...$extra,
        ])->save();

        if (Schema::hasTable('notifications') && $booking->requestedBy) {
            $booking->requestedBy->notify(new DjBookingEventNotification($booking->load('profile'), $notificationEvent));
        }

        return response()->json([
            'booking' => $this->bookingPayload($booking->refresh()),
        ]);
    }

    private function bookingForDj(Request $request, string $uuid): DjBookingRequest
    {
        return DjBookingRequest::query()
            ->with(['profile', 'requestedBy'])
            ->forDjUser($request->user()->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function assertStatus(DjBookingRequest $booking, array $statuses, string $message): void
    {
        if (in_array($booking->status, $statuses, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'booking' => [$message],
        ]);
    }

    private function analytics($baseQuery): array
    {
        return [
            'total_booking_requests' => (clone $baseQuery)->count(),
            'pending_requests' => (clone $baseQuery)->where('status', DjBookingRequest::STATUS_PENDING)->count(),
            'accepted_bookings' => (clone $baseQuery)->where('status', DjBookingRequest::STATUS_ACCEPTED)->count(),
            'needs_discussion' => (clone $baseQuery)->where('status', DjBookingRequest::STATUS_NEEDS_DISCUSSION)->count(),
            'completed_bookings' => (clone $baseQuery)->where('status', DjBookingRequest::STATUS_COMPLETED)->count(),
            'cancelled_bookings' => (clone $baseQuery)->where('status', DjBookingRequest::STATUS_CANCELLED)->count(),
            'estimated_booking_value' => (float) (clone $baseQuery)->sum('estimated_total_amount'),
            'paid_external' => (float) (clone $baseQuery)->where('payment_status', DjBookingRequest::PAYMENT_PAID_EXTERNAL)->sum('estimated_total_amount'),
            'unpaid_accepted_bookings' => (clone $baseQuery)
                ->where('status', DjBookingRequest::STATUS_ACCEPTED)
                ->where('payment_status', DjBookingRequest::PAYMENT_UNPAID)
                ->count(),
            'upcoming_events' => (clone $baseQuery)
                ->whereIn('status', [DjBookingRequest::STATUS_ACCEPTED, DjBookingRequest::STATUS_NEEDS_DISCUSSION])
                ->whereDate('event_date', '>=', now()->toDateString())
                ->count(),
        ];
    }

    private function bookingStatuses(): array
    {
        return [
            DjBookingRequest::STATUS_PENDING,
            DjBookingRequest::STATUS_NEEDS_DISCUSSION,
            DjBookingRequest::STATUS_ACCEPTED,
            DjBookingRequest::STATUS_DECLINED,
            DjBookingRequest::STATUS_CANCELLED,
            DjBookingRequest::STATUS_COMPLETED,
            DjBookingRequest::STATUS_EXPIRED,
        ];
    }

    private function paymentStatuses(): array
    {
        return [
            DjBookingRequest::PAYMENT_UNPAID,
            DjBookingRequest::PAYMENT_PENDING_EXTERNAL,
            DjBookingRequest::PAYMENT_PAID_EXTERNAL,
            DjBookingRequest::PAYMENT_REFUNDED_EXTERNAL,
            DjBookingRequest::PAYMENT_NOT_REQUIRED,
        ];
    }
}
