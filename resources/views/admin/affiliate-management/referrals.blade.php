@extends('admin.layouts.app', [
    'title' => 'Affiliate Referral Management',
    'heading' => 'Affiliate Referral Management',
    'subtitle' => 'Review signup attribution, qualification status, and referral activity.',
])

@section('admin_content')
    @php
        $badge = fn (string $status): string => match ($status) {
            'pending' => 'secondary',
            'qualified' => 'success',
            'rejected' => 'danger',
            default => 'secondary',
        };
        $label = fn (?string $value): string => str((string) ($value ?: 'none'))->replace('_', ' ')->headline()->toString();
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
                    <h3>{{ $stats['total'] }}</h3>
                    <p>Total Referrals</p>
                </div>
                <div class="icon"><i class="fas fa-route"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $stats['pending'] }}</h3>
                    <p>Pending</p>
                </div>
                <div class="icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['qualified'] }}</h3>
                    <p>Qualified</p>
                </div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['events'] }}</h3>
                    <p>Qualification Events</p>
                </div>
                <div class="icon"><i class="fas fa-history"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Referral Search</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.admincenter.affiliatereferrals.index') }}">
                <div class="row">
                    <div class="form-group col-lg-6">
                        <label for="referral_search">Search</label>
                        <input id="referral_search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Affiliate, referred user, code, transaction, or reason">
                    </div>
                    <div class="form-group col-lg-3">
                        <label for="referral_status">Status</label>
                        <select id="referral_status" name="status" class="form-control">
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
                        <a href="{{ route('admin.admincenter.affiliatereferrals.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Referrals</h3>
            <div class="card-tools text-muted">
                {{ $stats['rejected'] }} rejected, {{ $stats['suspicious_referrals'] }} fraud review, {{ $stats['suspicious_visits'] }} suspicious visits
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Affiliate</th>
                            <th>Referred User</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Fraud</th>
                            <th>Qualification</th>
                            <th>Activity</th>
                            <th class="text-right">Status Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($referrals as $referral)
                            <tr>
                                <td>
                                    <strong>{{ $referral->affiliateAccount?->display_name ?: $referral->affiliateAccount?->user?->name }}</strong>
                                    <div class="small text-muted">{{ $referral->affiliateAccount?->contact_email ?: $referral->affiliateAccount?->user?->email }}</div>
                                </td>
                                <td>
                                    <strong>{{ $referral->referredUser?->name }}</strong>
                                    <div class="small text-muted">{{ $referral->referredUser?->email }}</div>
                                </td>
                                <td><span class="badge badge-light border">{{ $referral->referralCode?->code ?? 'No code' }}</span></td>
                                <td><span class="badge badge-{{ $badge($referral->status) }}">{{ $label($referral->status) }}</span></td>
                                <td>
                                    @if ($referral->is_suspicious)
                                        <span class="badge badge-warning">Review</span>
                                        <div class="small text-muted">{{ $label($referral->fraud_reason ?: $referral->rejection_reason) }}</div>
                                    @else
                                        <span class="badge badge-success">Clear</span>
                                    @endif

                                    @if ($referral->referralVisit?->is_suspicious)
                                        <div class="small text-warning">Visit: {{ $label($referral->referralVisit->suspicious_reason) }}</div>
                                    @endif

                                    @if (($referral->metadata['ip_hash_matches_visit'] ?? null) !== null || ($referral->metadata['user_agent_hash_matches_visit'] ?? null) !== null)
                                        <div class="small text-muted">
                                            IP {{ ($referral->metadata['ip_hash_matches_visit'] ?? null) === false ? 'mismatch' : 'match' }},
                                            UA {{ ($referral->metadata['user_agent_hash_matches_visit'] ?? null) === false ? 'mismatch' : 'match' }}
                                        </div>
                                    @endif

                                    @if ($referral->fraud_flags)
                                        <div class="small text-muted">{{ collect($referral->fraud_flags)->map(fn ($flag) => $label($flag))->join(', ') }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($referral->qualified_at)
                                        {{ $referral->qualified_at->format('M j, Y') }}
                                        <div class="small text-muted">{{ $referral->qualified_transaction_type }} {{ $referral->qualified_transaction_id }}</div>
                                    @elseif ($referral->rejected_at)
                                        {{ $referral->rejected_at->format('M j, Y') }}
                                        <div class="small text-muted">{{ $referral->rejection_reason }}</div>
                                    @else
                                        <span class="text-muted">Awaiting subscription</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $referral->events_count }} events
                                    <div class="small text-muted">{{ $referral->rewards_count }} rewards</div>
                                </td>
                                <td class="text-right" style="min-width: 360px;">
                                    @can('affiliatereferrals.update')
                                        <form method="POST" action="{{ route('admin.admincenter.affiliatereferrals.status.update', $referral) }}">
                                            @csrf
                                            @method('PATCH')
                                            <div class="form-row justify-content-end">
                                                <div class="col-auto">
                                                    <select name="status" class="form-control form-control-sm">
                                                        @foreach ($statuses as $status)
                                                            <option value="{{ $status }}" @selected($referral->status === $status)>{{ $label($status) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-auto">
                                                    <input type="text" name="qualified_transaction_type" class="form-control form-control-sm" placeholder="Provider" value="{{ $referral->qualified_transaction_type }}">
                                                </div>
                                                <div class="col-auto">
                                                    <input type="text" name="qualified_transaction_id" class="form-control form-control-sm" placeholder="Transaction" value="{{ $referral->qualified_transaction_id }}">
                                                </div>
                                                <div class="col-auto">
                                                    <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Reason" value="{{ $referral->rejection_reason }}">
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
                                <td colspan="8" class="text-center text-muted py-4">No referrals match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($referrals->hasPages())
            <div class="card-footer">
                {{ $referrals->links() }}
            </div>
        @endif
    </div>
@endsection
