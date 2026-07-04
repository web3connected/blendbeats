@extends('admin.layouts.app', [
    'title' => 'DJ Bookings',
    'heading' => 'DJ Bookings',
    'subtitle' => 'Review DJ booking requests, status, contact details, and manual payment state.',
])

@php
    $label = fn (string $value): string => str($value)->replace('_', ' ')->headline()->toString();
    $statusTheme = fn (string $status): string => match ($status) {
        'pending' => 'warning',
        'needs_discussion' => 'info',
        'accepted', 'completed' => 'success',
        'declined', 'cancelled', 'expired' => 'secondary',
        default => 'light',
    };
    $paymentTheme = fn (string $status): string => match ($status) {
        'paid_external' => 'success',
        'pending_external_payment' => 'warning',
        'refunded_external' => 'info',
        'not_required' => 'secondary',
        default => 'danger',
    };
@endphp

@section('admin_content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Booking Requests</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-12 col-lg-4">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input id="search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Event, DJ, customer, email">
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $label($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="payment_status">Payment Status</label>
                        <select id="payment_status" name="payment_status" class="form-control">
                            <option value="">All payment statuses</option>
                            @foreach ($paymentStatuses as $status)
                                <option value="{{ $status }}" @selected(($filters['payment_status'] ?? null) === $status)>{{ $label($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-12 col-lg-2 d-flex align-items-end">
                    <div class="form-group w-100">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>DJ</th>
                            <th>Customer</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th class="text-right">Estimate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bookings as $booking)
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $booking->event_name }}</div>
                                    <div class="text-muted small">{{ $booking->event_type }} / {{ $booking->uuid }}</div>
                                </td>
                                <td>
                                    <div>{{ $booking->profile?->dj_name ?? 'Unknown DJ' }}</div>
                                    <div class="text-muted small">{{ $booking->profile?->handle ? '@'.$booking->profile->handle : '' }}</div>
                                </td>
                                <td>
                                    <div>{{ $booking->contact_name }}</div>
                                    <div class="text-muted small">{{ $booking->contact_email }}</div>
                                </td>
                                <td>
                                    <div>{{ optional($booking->event_date)->format('M j, Y') }}</div>
                                    <div class="text-muted small">{{ substr((string) $booking->start_time, 0, 5) }} - {{ substr((string) $booking->end_time, 0, 5) }}</div>
                                </td>
                                <td><span class="badge badge-{{ $statusTheme($booking->status) }}">{{ $label($booking->status) }}</span></td>
                                <td><span class="badge badge-{{ $paymentTheme($booking->payment_status) }}">{{ $label($booking->payment_status) }}</span></td>
                                <td class="text-right">
                                    @if ($booking->estimated_total_amount !== null)
                                        {{ $booking->currency ?? 'USD' }} {{ number_format((float) $booking->estimated_total_amount, 2) }}
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No booking requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($bookings->hasPages())
            <div class="card-footer">
                {{ $bookings->links() }}
            </div>
        @endif
    </div>
@endsection
