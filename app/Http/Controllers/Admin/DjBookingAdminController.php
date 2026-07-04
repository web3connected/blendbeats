<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DjBookingRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class DjBookingAdminController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in($this->bookingStatuses())],
            'payment_status' => ['nullable', 'string', Rule::in($this->paymentStatuses())],
            'search' => ['nullable', 'string', 'max:150'],
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
            ->paginate(20)
            ->withQueryString();

        return view('admin.dj-bookings.index', [
            'bookings' => $bookings,
            'statuses' => $this->bookingStatuses(),
            'paymentStatuses' => $this->paymentStatuses(),
            'filters' => $filters,
        ]);
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
