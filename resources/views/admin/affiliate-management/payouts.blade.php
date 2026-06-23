@extends('admin.layouts.app', [
    'title' => 'Affiliate Payout Management',
    'heading' => 'Affiliate Payout Management',
    'subtitle' => 'Review payout requests, approve payable balances, and record payout processing.',
])

@section('admin_content')
    @php
        $badge = fn (string $status): string => match ($status) {
            'requested' => 'secondary',
            'approved' => 'info',
            'processing' => 'warning',
            'paid' => 'success',
            'rejected', 'cancelled' => 'danger',
            default => 'secondary',
        };
        $label = fn (?string $value): string => str((string) ($value ?: 'none'))->replace('_', ' ')->headline()->toString();
        $money = fn ($payout): string => $payout->currency.' '.number_format($payout->amount_cents / 100, 2);
    @endphp

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $stats['payable_balance'] }}</h3>
                    <p>Payable Balance</p>
                </div>
                <div class="icon"><i class="fas fa-wallet"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $stats['requested'] }}</h3>
                    <p>Requested Payouts</p>
                </div>
                <div class="icon"><i class="fas fa-inbox"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $stats['approved'] + $stats['processing'] }}</h3>
                    <p>Approved or Processing</p>
                </div>
                <div class="icon"><i class="fas fa-tasks"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['paid_amount'] }}</h3>
                    <p>Paid Out</p>
                </div>
                <div class="icon"><i class="fas fa-money-check-alt"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Payout Search</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.admincenter.affiliatepayouts.index') }}">
                <div class="row">
                    <div class="form-group col-lg-6">
                        <label for="payout_search">Search</label>
                        <input id="payout_search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Affiliate, method, reference, reason, or notes">
                    </div>
                    <div class="form-group col-lg-3">
                        <label for="payout_status">Status</label>
                        <select id="payout_status" name="status" class="form-control">
                            <option value="">Any status</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-lg-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-search mr-1"></i> Search
                        </button>
                        <a href="{{ route('admin.admincenter.affiliatepayouts.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Payout Requests</h3>
            <div class="card-tools text-muted">
                {{ $stats['total'] }} total, {{ $stats['rejected'] }} rejected, {{ $stats['cancelled'] }} cancelled
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Affiliate</th>
                            <th>Payout</th>
                            <th>Status</th>
                            <th>Timeline</th>
                            <th>Processing</th>
                            <th class="text-right">Status Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payouts as $payout)
                            <tr>
                                <td>
                                    <strong>{{ $payout->affiliateAccount?->display_name ?: $payout->affiliateAccount?->user?->name }}</strong>
                                    <div class="small text-muted">{{ $payout->affiliateAccount?->contact_email ?: $payout->affiliateAccount?->user?->email }}</div>
                                </td>
                                <td>
                                    <strong>{{ $money($payout) }}</strong>
                                    <div class="small text-muted">{{ $payout->reward_count }} rewards | {{ $label($payout->payment_method) }}</div>
                                </td>
                                <td><span class="badge badge-{{ $badge($payout->status) }}">{{ $label($payout->status) }}</span></td>
                                <td>
                                    Requested {{ $payout->requested_at?->format('M j, Y') ?? 'Not set' }}
                                    <div class="small text-muted">
                                        Approved {{ $payout->approved_at?->format('M j, Y') ?? 'No' }},
                                        Paid {{ $payout->paid_at?->format('M j, Y') ?? 'No' }}
                                    </div>
                                    @if ($payout->rejected_at || $payout->cancelled_at)
                                        <div class="small text-warning">{{ $payout->rejection_reason ?: 'No reason recorded' }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $payout->payout_reference ?: 'No reference' }}
                                    <div class="small text-muted">
                                        Processed by {{ $payout->processedByAdmin?->name ?? 'Not assigned' }}
                                    </div>
                                    @if ($payout->notes)
                                        <div class="small text-muted">{{ $payout->notes }}</div>
                                    @endif
                                </td>
                                <td class="text-right" style="min-width: 390px;">
                                    @can('affiliatepayouts.update')
                                        <form method="POST" action="{{ route('admin.admincenter.affiliatepayouts.status.update', $payout) }}">
                                            @csrf
                                            @method('PATCH')
                                            <div class="form-row justify-content-end">
                                                <div class="col-auto">
                                                    <select name="status" class="form-control form-control-sm">
                                                        @foreach ($statuses as $status)
                                                            <option value="{{ $status }}" @selected($payout->status === $status)>{{ $label($status) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-auto">
                                                    <input type="text" name="payout_reference" value="{{ $payout->payout_reference }}" class="form-control form-control-sm" placeholder="Reference">
                                                </div>
                                                <div class="col-auto">
                                                    <input type="text" name="rejection_reason" value="{{ $payout->rejection_reason }}" class="form-control form-control-sm" placeholder="Reason">
                                                </div>
                                                <div class="col-auto">
                                                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes">
                                                </div>
                                                <div class="col-auto">
                                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                                </div>
                                            </div>
                                        </form>
                                    @else
                                        <span class="text-muted">View only</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No payout requests match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($payouts->hasPages())
            <div class="card-footer">
                {{ $payouts->links() }}
            </div>
        @endif
    </div>
@endsection
