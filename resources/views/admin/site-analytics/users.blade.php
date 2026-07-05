@extends('admin.layouts.app', [
    'title' => 'User Activity',
    'heading' => 'User Activity',
    'subtitle' => 'Chart-first signed-in user behavior, registrations, active users, admin activity, and account engagement.',
])

@section('plugins.Chartjs', true)

@section('admin_content')
    @php
        $activityLabels = $userDailyActivity->pluck('label')->values();
        $userEvents = $userDailyActivity->pluck('user_events_count')->values();
        $activeUsers = $userDailyActivity->pluck('active_users_count')->values();
        $adminEvents = $userDailyActivity->pluck('admin_events_count')->values();
        $registrationLabels = $newUsersByDay->pluck('label')->values();
        $registrationCounts = $newUsersByDay->pluck('users_count')->values();
        $topUserLabels = $topUsers->map(fn ($activity) => \Illuminate\Support\Str::limit($activity->user?->name ?: 'Deleted User', 24))->values();
        $topUserEvents = $topUsers->pluck('events_count')->values();
        $topAdminLabels = $topAdmins->map(fn ($activity) => \Illuminate\Support\Str::limit($activity->admin?->name ?: 'Deleted Admin', 24))->values();
        $topAdminEvents = $topAdmins->pluck('events_count')->values();
        $topPathLabels = $topUserPaths->pluck('path')->map(fn ($path) => \Illuminate\Support\Str::limit($path, 38))->values();
        $topPathEvents = $topUserPaths->pluck('events_count')->values();
        $actorLabels = collect(['Guests', 'Users', 'Admins']);
        $actorEvents = collect([
            $actorBreakdown['guests'] ?? 0,
            $actorBreakdown['users'] ?? 0,
            $actorBreakdown['admins'] ?? 0,
        ]);
    @endphp

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div class="btn-group mb-2" role="group" aria-label="Analytics sections">
                    <a href="{{ route('admin.admincenter.site-analytics.index', request()->query()) }}" class="btn btn-outline-light">
                        <i class="fas fa-chart-area mr-1"></i> Site Traffic
                    </a>
                    <a href="{{ route('admin.admincenter.user-activity.index', request()->query()) }}" class="btn btn-primary">
                        <i class="fas fa-users mr-1"></i> User Activity
                    </a>
                </div>
                <span class="text-muted small mb-2">Updated {{ $generatedAt->format('M j, Y g:i A') }}</span>
            </div>

            <form method="GET" action="{{ route('admin.admincenter.user-activity.index') }}" class="form-row align-items-end">
                <div class="form-group col-12 col-md-3">
                    <label for="range">Date Range</label>
                    <select id="range" name="range" class="form-control">
                        <option value="7" @selected($range === '7')>Last 7 days</option>
                        <option value="30" @selected($range === '30')>Last 30 days</option>
                        <option value="90" @selected($range === '90')>Last 90 days</option>
                        <option value="custom" @selected($range === 'custom')>Custom range</option>
                    </select>
                </div>
                <div class="form-group col-12 col-md-3">
                    <label for="start_date">Start</label>
                    <input id="start_date" type="date" name="start_date" value="{{ $startDate }}" class="form-control">
                </div>
                <div class="form-group col-12 col-md-3">
                    <label for="end_date">End</label>
                    <input id="end_date" type="date" name="end_date" value="{{ $endDate }}" class="form-control">
                </div>
                <div class="form-group col-12 col-md-3">
                    <button class="btn btn-primary btn-block">
                        <i class="fas fa-filter mr-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        @foreach ($summaryCards as $card)
            <div class="col-12 col-md-6 col-xl">
                <div class="small-box bg-{{ $card['theme'] }}">
                    <div class="inner">
                        <h3>{{ $card['value'] }}</h3>
                        <p class="mb-1">{{ $card['label'] }}</p>
                        <span class="small">{{ $card['detail'] }}</span>
                    </div>
                    <div class="icon"><i class="{{ $card['icon'] }}"></i></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">User Activity Trend</h3>
                    <div class="card-tools text-muted">Signed-in events, active users, and admin events</div>
                </div>
                <div class="card-body">
                    <div style="height: 340px;">
                        <canvas id="userActivityTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Most Active Users</h3>
                    <div class="card-tools text-muted">Ranked by tracked signed-in events</div>
                </div>
                <div class="card-body">
                    <div style="height: 340px;">
                        <canvas id="topUsersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">New Registrations</h3>
                </div>
                <div class="card-body">
                    <div style="height: 240px;">
                        <canvas id="registrationsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Actor Mix</h3>
                </div>
                <div class="card-body">
                    <div style="height: 240px;">
                        <canvas id="actorChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Admin Activity</h3>
                </div>
                <div class="card-body">
                    <div style="height: 240px;">
                        <canvas id="topAdminsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top Signed-In User Paths</h3>
                </div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="topUserPathsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent User Signals</h3>
                </div>
                <div class="card-body">
                    @forelse ($recentEvents as $event)
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <strong>
                                    @if ($event->admin)
                                        {{ $event->admin->name }}
                                    @else
                                        {{ $event->user?->name ?: 'Deleted User' }}
                                    @endif
                                </strong>
                                <span class="badge badge-{{ $event->status_code >= 400 ? 'warning' : 'success' }}">{{ $event->status_code }}</span>
                            </div>
                            <div class="small text-muted">
                                {{ $event->method }} {{ $event->path }}
                            </div>
                            <div class="small text-muted">{{ $event->occurred_at->diffForHumans() }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No signed-in user activity has been tracked yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.Chart) return;

            Chart.defaults.global.defaultFontColor = '#adb5bd';
            Chart.defaults.global.defaultFontFamily = "'Source Sans Pro', Arial, sans-serif";

            var gridColor = 'rgba(255,255,255,0.08)';
            var colors = ['#3c8dbc', '#00bc8c', '#f39c12', '#e74c3c', '#6c757d', '#86bad8', '#f672d8', '#67ffa9'];
            var lineOptions = {
                responsive: true,
                maintainAspectRatio: false,
                legend: { labels: { boxWidth: 12 } },
                scales: {
                    xAxes: [{ gridLines: { color: gridColor } }],
                    yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: gridColor } }],
                },
            };
            var horizontalOptions = {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: gridColor } }],
                    yAxes: [{ gridLines: { display: false } }],
                },
            };
            var doughnutOptions = {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom', labels: { boxWidth: 12 } },
                cutoutPercentage: 62,
            };

            new Chart(document.getElementById('userActivityTrendChart'), {
                type: 'line',
                data: {
                    labels: @json($activityLabels),
                    datasets: [
                        { label: 'Signed-In Events', data: @json($userEvents), borderColor: '#3c8dbc', backgroundColor: 'rgba(60,141,188,0.15)', fill: true, lineTension: 0.25 },
                        { label: 'Active Users', data: @json($activeUsers), borderColor: '#00bc8c', backgroundColor: 'rgba(0,188,140,0.08)', fill: true, lineTension: 0.25 },
                        { label: 'Admin Events', data: @json($adminEvents), borderColor: '#f39c12', backgroundColor: 'rgba(243,156,18,0.08)', fill: true, lineTension: 0.25 },
                    ],
                },
                options: lineOptions,
            });

            new Chart(document.getElementById('registrationsChart'), {
                type: 'bar',
                data: {
                    labels: @json($registrationLabels),
                    datasets: [{ label: 'New Users', data: @json($registrationCounts), backgroundColor: '#00bc8c' }],
                },
                options: lineOptions,
            });

            new Chart(document.getElementById('topUsersChart'), {
                type: 'horizontalBar',
                data: {
                    labels: @json($topUserLabels),
                    datasets: [{ data: @json($topUserEvents), backgroundColor: '#3c8dbc' }],
                },
                options: horizontalOptions,
            });

            new Chart(document.getElementById('topAdminsChart'), {
                type: 'horizontalBar',
                data: {
                    labels: @json($topAdminLabels),
                    datasets: [{ data: @json($topAdminEvents), backgroundColor: '#f39c12' }],
                },
                options: horizontalOptions,
            });

            new Chart(document.getElementById('topUserPathsChart'), {
                type: 'horizontalBar',
                data: {
                    labels: @json($topPathLabels),
                    datasets: [{ data: @json($topPathEvents), backgroundColor: '#86bad8' }],
                },
                options: horizontalOptions,
            });

            new Chart(document.getElementById('actorChart'), {
                type: 'doughnut',
                data: {
                    labels: @json($actorLabels),
                    datasets: [{ data: @json($actorEvents), backgroundColor: colors }],
                },
                options: doughnutOptions,
            });
        });
    </script>
@endsection
