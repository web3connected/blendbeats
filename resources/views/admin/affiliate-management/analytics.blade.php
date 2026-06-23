@extends('admin.layouts.app', [
    'title' => 'Affiliate Analytics',
    'heading' => 'Affiliate Analytics',
    'subtitle' => 'Program-level affiliate statistics, conversion rates, and top affiliate performance.',
])

@section('admin_content')
    @php
        $stats = $analytics['statistics'];
        $rates = $analytics['conversion_rates'];
        $topAffiliates = collect($analytics['top_affiliates']);
        $campaigns = collect($analytics['campaigns'] ?? []);
        $payouts = $analytics['payouts'] ?? [];
        $percent = fn (float|int $value): string => number_format((float) $value, 2).'%';
        $money = fn (int|float $value): string => 'USD '.number_format(((int) $value) / 100, 2);
    @endphp

    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $stats['total_affiliates'] }}</h3>
                    <p>Total Affiliates</p>
                </div>
                <div class="icon"><i class="fas fa-handshake"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['active_affiliates'] }}</h3>
                    <p>Active Affiliates</p>
                </div>
                <div class="icon"><i class="fas fa-toggle-on"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['total_referral_visits'] }}</h3>
                    <p>Total Referral Visits</p>
                </div>
                <div class="icon"><i class="fas fa-eye"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $stats['total_attributed_signups'] }}</h3>
                    <p>Attributed Signups</p>
                </div>
                <div class="icon"><i class="fas fa-user-plus"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['total_qualified_referrals'] }}</h3>
                    <p>Qualified Referrals</p>
                </div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['total_membership_credits_issued'] }}</h3>
                    <p>Credits Issued</p>
                </div>
                <div class="icon"><i class="fas fa-gift"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $stats['total_membership_credits_redeemed'] }}</h3>
                    <p>Credits Redeemed</p>
                </div>
                <div class="icon"><i class="fas fa-ticket-alt"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $stats['total_membership_credits_expired'] }}</h3>
                    <p>Credits Expired</p>
                </div>
                <div class="icon"><i class="fas fa-hourglass-end"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Conversion Rates</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small text-uppercase">Visit to Signup</div>
                        <div class="h3 mb-0">{{ $percent($rates['visit_to_signup_rate']) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small text-uppercase">Signup to Qualified</div>
                        <div class="h3 mb-0">{{ $percent($rates['signup_to_qualified_rate']) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small text-uppercase">Visit to Qualified</div>
                        <div class="h3 mb-0">{{ $percent($rates['visit_to_qualified_rate']) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Payout Analytics</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small text-uppercase">Payable Balance</div>
                        <div class="h3 mb-0">{{ $money($payouts['payable_balance_cents'] ?? 0) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small text-uppercase">Requested Amount</div>
                        <div class="h3 mb-0">{{ $money($payouts['requested_amount_cents'] ?? 0) }}</div>
                        <div class="small text-muted">{{ $payouts['requested_count'] ?? 0 }} requested, {{ $payouts['approved_count'] ?? 0 }} approved</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small text-uppercase">Paid Amount</div>
                        <div class="h3 mb-0">{{ $money($payouts['paid_amount_cents'] ?? 0) }}</div>
                        <div class="small text-muted">{{ $payouts['paid_count'] ?? 0 }} paid, {{ $payouts['rejected_count'] ?? 0 }} rejected</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Campaign Performance</h3>
            <div class="card-tools text-muted">Grouped by assigned referral campaign.</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Status</th>
                            <th>Codes</th>
                            <th>Visits</th>
                            <th>Signups</th>
                            <th>Qualified</th>
                            <th>Credits</th>
                            <th>Conversion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaigns as $campaign)
                            <tr>
                                <td>
                                    <strong>{{ $campaign['name'] }}</strong>
                                    <div class="small text-muted">{{ $campaign['slug'] }}</div>
                                </td>
                                <td><span class="badge badge-{{ $campaign['status'] === 'active' ? 'success' : 'secondary' }}">{{ str($campaign['status'])->headline() }}</span></td>
                                <td>{{ $campaign['referral_codes'] }}</td>
                                <td>{{ $campaign['referral_visits'] }}</td>
                                <td>{{ $campaign['attributed_signups'] }}</td>
                                <td>{{ $campaign['qualified_referrals'] }}</td>
                                <td>
                                    {{ $campaign['membership_credits_issued'] }} issued
                                    <div class="small text-muted">
                                        {{ $campaign['membership_credits_redeemed'] }} redeemed, {{ $campaign['membership_credits_expired'] }} expired
                                    </div>
                                </td>
                                <td>
                                    {{ $percent($campaign['visit_to_signup_rate']) }} signup
                                    <div class="small text-muted">{{ $percent($campaign['visit_to_qualified_rate']) }} qualified</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No campaign analytics are available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Top Affiliates Leaderboard</h3>
            <div class="card-tools text-muted">Ranked by qualified referrals, signups, then visits.</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Affiliate</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Visits</th>
                            <th>Signups</th>
                            <th>Qualified</th>
                            <th>Credits</th>
                            <th>Conversion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topAffiliates as $affiliate)
                            <tr>
                                <td><strong>#{{ $affiliate['rank'] }}</strong></td>
                                <td>
                                    <strong>{{ $affiliate['display_name'] }}</strong>
                                    <div class="small text-muted">{{ $affiliate['contact_email'] }}</div>
                                </td>
                                <td><span class="badge badge-light border">{{ $affiliate['referral_code'] ?? 'No code' }}</span></td>
                                <td><span class="badge badge-{{ $affiliate['status'] === 'active' ? 'success' : 'secondary' }}">{{ str($affiliate['status'])->headline() }}</span></td>
                                <td>{{ $affiliate['referral_visits'] }}</td>
                                <td>{{ $affiliate['attributed_signups'] }}</td>
                                <td>{{ $affiliate['qualified_referrals'] }}</td>
                                <td>
                                    {{ $affiliate['membership_credits_issued'] }} issued
                                    <div class="small text-muted">
                                        {{ $affiliate['membership_credits_redeemed'] }} redeemed, {{ $affiliate['membership_credits_expired'] }} expired
                                    </div>
                                </td>
                                <td>
                                    {{ $percent($affiliate['visit_to_signup_rate']) }} signup
                                    <div class="small text-muted">{{ $percent($affiliate['signup_to_qualified_rate']) }} qualified</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No affiliate analytics are available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
