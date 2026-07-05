@extends('admin.layouts.app', [
    'title' => 'Site Analytics',
    'heading' => 'Site Analytics',
    'subtitle' => 'Track site traffic, signed-in user activity, admin activity, referrers, devices, and response health.',
])

@section('admin_content')
    @php
        $maxDailyEvents = max($dailyTraffic->max('events_count') ?? 0, 1);
        $maxDeviceEvents = max($deviceBreakdown->max('events_count') ?? 0, 1);
        $statusTotal = max(array_sum($statusBreakdown), 1);
    @endphp

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.admincenter.site-analytics.index') }}" class="form-row align-items-end">
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
            <p class="text-muted small mb-0">
                Updated {{ $generatedAt->format('M j, Y g:i A') }}. Activity tracking stores request metadata only; request bodies are not recorded.
            </p>
        </div>
    </div>

    <div class="row">
        @foreach ($summaryCards as $card)
            <div class="col-12 col-md-6 col-xl-3">
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
                    <h3 class="card-title">Traffic By Day</h3>
                    <div class="card-tools text-muted">Events, visitors, and signed-in users</div>
                </div>
                <div class="card-body">
                    @forelse ($dailyTraffic as $day)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $day['label'] }}</strong>
                                <span class="text-muted small">
                                    {{ number_format($day['events_count']) }} events
                                    <span class="mx-1">&middot;</span>
                                    {{ number_format($day['visitors_count']) }} visitors
                                    <span class="mx-1">&middot;</span>
                                    {{ number_format($day['users_count']) }} users
                                </span>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-primary" style="width: {{ max(($day['events_count'] / $maxDailyEvents) * 100, $day['events_count'] > 0 ? 3 : 0) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No activity has been tracked for this range yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top Pages</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Page</th>
                                    <th>Route</th>
                                    <th>Events</th>
                                    <th>Visitors</th>
                                    <th>Avg</th>
                                    <th class="text-right">Last Seen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topPages as $page)
                                    <tr>
                                        <td><code>{{ $page->path }}</code></td>
                                        <td>{{ $page->route_name ?: 'Unnamed route' }}</td>
                                        <td>{{ number_format($page->events_count) }}</td>
                                        <td>{{ number_format($page->visitors_count) }}</td>
                                        <td>{{ number_format((float) $page->average_duration_ms) }} ms</td>
                                        <td class="text-right text-muted small">{{ \Illuminate\Support\Carbon::parse($page->last_seen_at)->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No page activity yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Activity Events</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Actor</th>
                                    <th>Request</th>
                                    <th>Status</th>
                                    <th>Device</th>
                                    <th class="text-right">Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentEvents as $event)
                                    <tr>
                                        <td class="text-muted small">{{ $event->occurred_at->diffForHumans() }}</td>
                                        <td>
                                            @if ($event->admin)
                                                <strong>{{ $event->admin->name }}</strong>
                                                <div class="small text-muted">Admin</div>
                                            @elseif ($event->user)
                                                <strong>{{ $event->user->name ?: 'Unnamed User' }}</strong>
                                                <div class="small text-muted">{{ $event->user->email }}</div>
                                            @else
                                                <span class="text-muted">Guest</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-light border">{{ $event->method }}</span>
                                            <code>{{ $event->path }}</code>
                                            @if ($event->route_name)
                                                <div class="small text-muted">{{ $event->route_name }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $event->status_code >= 500 ? 'danger' : ($event->status_code >= 400 ? 'warning' : ($event->status_code >= 300 ? 'info' : 'success')) }}">
                                                {{ $event->status_code }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ str($event->device_type ?? 'unknown')->headline() }}
                                            @if ($event->is_bot)
                                                <span class="badge badge-secondary ml-1">Bot</span>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format($event->duration_ms ?? 0) }} ms</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No recent activity has been tracked yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Response Health</h3>
                </div>
                <div class="card-body">
                    @foreach ([
                        'success' => ['label' => 'Success', 'theme' => 'success'],
                        'redirects' => ['label' => 'Redirects', 'theme' => 'info'],
                        'client_errors' => ['label' => 'Client Errors', 'theme' => 'warning'],
                        'server_errors' => ['label' => 'Server Errors', 'theme' => 'danger'],
                    ] as $key => $meta)
                        @php $value = $statusBreakdown[$key] ?? 0; @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $meta['label'] }}</strong>
                                <span>{{ number_format($value) }}</span>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-{{ $meta['theme'] }}" style="width: {{ ($value / $statusTotal) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Device Breakdown</h3>
                </div>
                <div class="card-body">
                    @forelse ($deviceBreakdown as $device)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <strong>{{ str($device->device_type)->headline() }}</strong>
                                <span>{{ number_format($device->events_count) }}</span>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-info" style="width: {{ max(($device->events_count / $maxDeviceEvents) * 100, 3) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No device data yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top Referrers</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($topReferrers as $referrer)
                                <tr>
                                    <td>
                                        <strong>{{ $referrer->referrer_host }}</strong>
                                        <div class="small text-muted">{{ number_format($referrer->visitors_count) }} visitors</div>
                                    </td>
                                    <td class="text-right">{{ number_format($referrer->events_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-muted py-4">No referrers tracked yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Most Active Users</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($topUsers as $activity)
                                <tr>
                                    <td>
                                        <strong>{{ $activity->user?->name ?: 'Deleted User' }}</strong>
                                        <div class="small text-muted">{{ $activity->user?->email }}</div>
                                    </td>
                                    <td class="text-right">
                                        {{ number_format($activity->events_count) }}
                                        <div class="small text-muted">{{ number_format($activity->active_days) }} days</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-muted py-4">No signed-in user activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Admin Activity</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($topAdmins as $activity)
                                <tr>
                                    <td>
                                        <strong>{{ $activity->admin?->name ?: 'Deleted Admin' }}</strong>
                                        <div class="small text-muted">{{ $activity->admin?->role ?: 'Admin' }}</div>
                                    </td>
                                    <td class="text-right">{{ number_format($activity->events_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-muted py-4">No admin activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
