<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\SerializesDjBookingRequests;
use App\Http\Controllers\Controller;
use App\Models\DjBookingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDjBookingController extends Controller
{
    use SerializesDjBookingRequests;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                DjBookingRequest::STATUS_PENDING,
                DjBookingRequest::STATUS_NEEDS_DISCUSSION,
                DjBookingRequest::STATUS_ACCEPTED,
                DjBookingRequest::STATUS_DECLINED,
                DjBookingRequest::STATUS_CANCELLED,
                DjBookingRequest::STATUS_COMPLETED,
                DjBookingRequest::STATUS_EXPIRED,
            ])],
            'payment_status' => ['nullable', 'string', Rule::in([
                DjBookingRequest::PAYMENT_UNPAID,
                DjBookingRequest::PAYMENT_PENDING_EXTERNAL,
                DjBookingRequest::PAYMENT_PAID_EXTERNAL,
                DjBookingRequest::PAYMENT_REFUNDED_EXTERNAL,
                DjBookingRequest::PAYMENT_NOT_REQUIRED,
            ])],
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $bookings = DjBookingRequest::query()
            ->with(['profile', 'requestedBy'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn ($query, string $status) => $query->where('payment_status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('event_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn ($profileQuery) => $profileQuery->where('dj_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return response()->json([
            'bookings' => collect($bookings->items())
                ->map(fn (DjBookingRequest $booking): array => $this->bookingPayload($booking))
                ->values(),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function show(string $booking): JsonResponse
    {
        $bookingRequest = DjBookingRequest::query()
            ->with(['profile', 'requestedBy'])
            ->where('uuid', $booking)
            ->firstOrFail();

        return response()->json([
            'booking' => $this->bookingPayload($bookingRequest),
        ]);
    }
}
