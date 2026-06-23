@extends('admin.layouts.app', [
    'title' => 'Affiliate Management',
    'heading' => 'Affiliate Management',
    'subtitle' => 'Manage affiliate accounts, statuses, referral totals, and program participation.',
])

@section('admin_content')
    @php
        $badge = fn (string $status): string => match ($status) {
            'active' => 'success',
            'paused' => 'warning',
            'banned' => 'danger',
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
                    <p>Total Affiliates</p>
                </div>
                <div class="icon"><i class="fas fa-handshake"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['active'] }}</h3>
                    <p>Active Affiliates</p>
                </div>
                <div class="icon"><i class="fas fa-toggle-on"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['qualified_referrals'] }}</h3>
                    <p>Qualified Referrals</p>
                </div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $stats['rewards'] }}</h3>
                    <p>Rewards Created</p>
                </div>
                <div class="icon"><i class="fas fa-award"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Affiliate Search</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.admincenter.affiliates.index') }}">
                <div class="row">
                    <div class="form-group col-lg-6">
                        <label for="affiliate_search">Search</label>
                        <input id="affiliate_search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Name, email, display name, or referral code">
                    </div>
                    <div class="form-group col-lg-3">
                        <label for="affiliate_status">Status</label>
                        <select id="affiliate_status" name="status" class="form-control">
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
                        <a href="{{ route('admin.admincenter.affiliates.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Affiliate Accounts</h3>
            <div class="card-tools text-muted">
                {{ $stats['paused'] }} paused, {{ $stats['banned'] }} banned
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Affiliate</th>
                            <th>Referral Code</th>
                            <th>Status</th>
                            <th>Referrals</th>
                            <th>Rewards</th>
                            <th>Joined</th>
                            <th class="text-right">Status Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($affiliates as $affiliate)
                            <tr>
                                <td>
                                    <strong>{{ $affiliate->display_name ?: $affiliate->user?->name }}</strong>
                                    <div class="small text-muted">{{ $affiliate->contact_email ?: $affiliate->user?->email }}</div>
                                </td>
                                <td>
                                    @if ($affiliate->defaultReferralCode)
                                        <span class="badge badge-light border">{{ $affiliate->defaultReferralCode->code }}</span>
                                    @else
                                        <span class="text-muted">No default code</span>
                                    @endif
                                </td>
                                <td><span class="badge badge-{{ $badge($affiliate->status) }}">{{ $label($affiliate->status) }}</span></td>
                                <td>
                                    {{ $affiliate->referrals_count }}
                                    <div class="small text-muted">{{ $affiliate->qualified_referrals_count }} qualified</div>
                                </td>
                                <td>{{ $affiliate->rewards_count }}</td>
                                <td>{{ $affiliate->joined_at?->format('M j, Y') ?? 'Not set' }}</td>
                                <td class="text-right">
                                    @can('affiliates.update')
                                        <form method="POST" action="{{ route('admin.admincenter.affiliates.status.update', $affiliate) }}" class="form-inline justify-content-end">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="form-control form-control-sm mr-2">
                                                @foreach ($statuses as $status)
                                                    <option value="{{ $status }}" @selected($affiliate->status === $status)>{{ $label($status) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                        </form>
                                    @else
                                        <span class="text-muted">View only</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No affiliate accounts match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($affiliates->hasPages())
            <div class="card-footer">
                {{ $affiliates->links() }}
            </div>
        @endif
    </div>
@endsection
