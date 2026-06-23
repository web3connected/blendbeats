@extends('admin.layouts.app', [
    'title' => 'Affiliate Reward Management',
    'heading' => 'Affiliate Reward Management',
    'subtitle' => 'Manage reward status, issuance references, and reward audit activity.',
])

@section('admin_content')
    @php
        $badge = fn (string $status): string => match ($status) {
            'pending' => 'secondary',
            'approved' => 'info',
            'issued' => 'success',
            'paid' => 'primary',
            'redeemed' => 'success',
            'expired' => 'warning',
            'cancelled', 'voided' => 'danger',
            default => 'secondary',
        };
        $label = fn (?string $value): string => str((string) ($value ?: 'none'))->replace('_', ' ')->headline()->toString();
        $amount = fn ($reward): string => $reward->amount_cents === null
            ? 'Not set'
            : $reward->currency.' '.number_format($reward->amount_cents / 100, 2);
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
                    <p>Total Rewards</p>
                </div>
                <div class="icon"><i class="fas fa-award"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $stats['pending'] }}</h3>
                    <p>Pending</p>
                </div>
                <div class="icon"><i class="fas fa-hourglass-half"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['approved'] }}</h3>
                    <p>Approved</p>
                </div>
                <div class="icon"><i class="fas fa-thumbs-up"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['issued'] + $stats['redeemed'] }}</h3>
                    <p>Issued or Redeemed</p>
                </div>
                <div class="icon"><i class="fas fa-paper-plane"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Reward Search</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.admincenter.affiliaterewards.index') }}">
                <div class="row">
                    <div class="form-group col-lg-6">
                        <label for="reward_search">Search</label>
                        <input id="reward_search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Affiliate, referred user, code, type, source, or reference">
                    </div>
                    <div class="form-group col-lg-3">
                        <label for="reward_status">Status</label>
                        <select id="reward_status" name="status" class="form-control">
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
                        <a href="{{ route('admin.admincenter.affiliaterewards.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Rewards</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Affiliate</th>
                            <th>Referral</th>
                            <th>Reward</th>
                            <th>Status</th>
                            <th>Issuance</th>
                            <th>Expiration</th>
                            <th>Audit</th>
                            <th class="text-right">Status Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rewards as $reward)
                            <tr>
                                <td>
                                    <strong>{{ $reward->affiliateAccount?->display_name ?: $reward->affiliateAccount?->user?->name }}</strong>
                                    <div class="small text-muted">{{ $reward->affiliateAccount?->contact_email ?: $reward->affiliateAccount?->user?->email }}</div>
                                </td>
                                <td>
                                    <strong>{{ $reward->referral?->referredUser?->name }}</strong>
                                    <div class="small text-muted">{{ $reward->referral?->referralCode?->code ?? 'No code' }}</div>
                                </td>
                                <td>
                                    {{ $label($reward->reward_type) }}
                                    <div class="small text-muted">
                                        {{ $label($reward->source) }} | {{ $amount($reward) }} | Qty {{ $reward->quantity }}
                                    </div>
                                </td>
                                <td><span class="badge badge-{{ $badge($reward->status) }}">{{ $label($reward->status) }}</span></td>
                                <td>
                                    @if ($reward->issued_at)
                                        {{ $reward->issued_at->format('M j, Y') }}
                                        <div class="small text-muted">{{ $reward->issued_reference ?: 'No reference' }}</div>
                                    @elseif ($reward->approved_at)
                                        Approved {{ $reward->approved_at->format('M j, Y') }}
                                    @else
                                        <span class="text-muted">Not issued</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($reward->reward_type === 'membership_credit')
                                        <strong>{{ $reward->membership_credit_days ?? 0 }} days</strong>
                                        <div class="small text-muted">
                                            Redeem by {{ $reward->expires_at?->format('M j, Y') ?? 'No expiration' }}
                                        </div>
                                        @if ($reward->redeemed_at)
                                            <div class="small text-success">Redeemed {{ $reward->redeemed_at->format('M j, Y') }}</div>
                                        @elseif ($reward->expires_at && $reward->expires_at->isPast())
                                            <div class="small text-warning">Expired unused</div>
                                        @endif
                                    @else
                                        <span class="text-muted">Not a membership credit</span>
                                    @endif
                                </td>
                                <td>{{ $reward->audits_count }} audit records</td>
                                <td class="text-right" style="min-width: 330px;">
                                    @can('affiliaterewards.update')
                                        <form method="POST" action="{{ route('admin.admincenter.affiliaterewards.status.update', $reward) }}">
                                            @csrf
                                            @method('PATCH')
                                            <div class="form-row justify-content-end">
                                                <div class="col-auto">
                                                    <select name="status" class="form-control form-control-sm">
                                                        @foreach ($statuses as $status)
                                                            <option value="{{ $status }}" @selected($reward->status === $status)>{{ $label($status) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-auto">
                                                    <input type="text" name="issued_reference" value="{{ $reward->issued_reference }}" class="form-control form-control-sm" placeholder="Reference">
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
                                <td colspan="8" class="text-center text-muted py-4">No rewards match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($rewards->hasPages())
            <div class="card-footer">
                {{ $rewards->links() }}
            </div>
        @endif
    </div>
@endsection
