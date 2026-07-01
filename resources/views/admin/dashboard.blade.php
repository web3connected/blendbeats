@extends('admin.layouts.app', [
    'title' => 'Dashboard',
    'heading' => 'Dashboard',
    'subtitle' => 'Platform control room for battle activity, token economy, escrow health, and admin review.',
])

@section('admin_content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Admin Alerts</h2>
            <p class="text-muted mb-0">Items that need attention right now.</p>
        </div>
        <span class="text-muted small">Updated {{ $generatedAt->format('M j, Y g:i A') }}</span>
    </div>

    <div class="row">
        @foreach ($adminAlerts as $alert)
            <div class="col-12 col-md-6 col-xl">
                <div class="card card-outline card-{{ $alert['theme'] }} h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <div class="text-muted text-uppercase small font-weight-bold">{{ $alert['label'] }}</div>
                                <div class="display-4 font-weight-bold mb-1">{{ number_format($alert['value']) }}</div>
                                <div class="text-muted small">{{ $alert['detail'] }}</div>
                            </div>
                            <span class="btn btn-{{ $alert['theme'] }} btn-sm disabled">
                                <i class="{{ $alert['icon'] }}"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @foreach ($sections as $section)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ $section['title'] }}</h3>
            </div>
            <div class="card-body pb-0">
                <div class="row">
                    @foreach ($section['cards'] as $card)
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-{{ $card['theme'] }}">
                                    <i class="{{ $card['icon'] }}"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">{{ $card['label'] }}</span>
                                    <span class="info-box-number">{{ $card['value'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    <div class="row">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top Voted Battles</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Battle</th>
                                <th>Status</th>
                                <th class="text-right">Votes</th>
                                <th class="text-right">Minimum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topVotedBattles as $battle)
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $battle->title }}</div>
                                        <div class="text-muted small">{{ $battle->uuid }}</div>
                                    </td>
                                    <td><span class="badge badge-secondary">{{ str($battle->status)->headline() }}</span></td>
                                    <td class="text-right">{{ number_format($battle->submitted_votes_count) }}</td>
                                    <td class="text-right">{{ number_format($battle->minimum_votes) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No submitted battle votes yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Most Active Voters</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th class="text-right">Votes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($mostActiveVoters as $voter)
                                <tr>
                                    <td>{{ $voter->name }}</td>
                                    <td class="text-muted">{{ $voter->email }}</td>
                                    <td class="text-right">{{ number_format($voter->submitted_votes_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No submitted voter activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
