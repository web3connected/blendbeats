<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteActivityEvent;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SiteAnalyticsController extends Controller
{
    public function __invoke(Request $request): View
    {
        return $this->site($request);
    }

    public function site(Request $request): View
    {
        [$start, $end, $range] = $this->dateRange($request);

        $baseQuery = SiteActivityEvent::query()
            ->whereBetween('occurred_at', [$start, $end]);

        return view('admin.site-analytics.index', [
            'range' => $range,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'generatedAt' => now(),
            'summaryCards' => $this->siteSummaryCards($baseQuery),
            'dailyTraffic' => $this->dailyTraffic($baseQuery, $start, $end),
            'topPages' => $this->topPages($baseQuery),
            'topReferrers' => $this->topReferrers($baseQuery),
            'deviceBreakdown' => $this->deviceBreakdown($baseQuery),
            'statusBreakdown' => $this->statusBreakdown($baseQuery),
            'actorBreakdown' => $this->actorBreakdown($baseQuery),
        ]);
    }

    public function users(Request $request): View
    {
        [$start, $end, $range] = $this->dateRange($request);

        $baseQuery = SiteActivityEvent::query()
            ->whereBetween('occurred_at', [$start, $end]);

        return view('admin.site-analytics.users', [
            'range' => $range,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'generatedAt' => now(),
            'summaryCards' => $this->userSummaryCards($baseQuery, $start, $end),
            'userDailyActivity' => $this->userDailyActivity($baseQuery, $start, $end),
            'newUsersByDay' => $this->newUsersByDay($start, $end),
            'topUsers' => $this->topUsers($baseQuery),
            'topAdmins' => $this->topAdmins($baseQuery),
            'topUserPaths' => $this->topUserPaths($baseQuery),
            'actorBreakdown' => $this->actorBreakdown($baseQuery),
            'recentEvents' => $this->recentUserEvents($baseQuery),
        ]);
    }

    private function dateRange(Request $request): array
    {
        $range = (string) $request->query('range', '30');
        $today = now();

        if ($range === 'custom') {
            $start = Carbon::parse((string) $request->query('start_date', $today->copy()->subDays(29)->toDateString()))->startOfDay();
            $end = Carbon::parse((string) $request->query('end_date', $today->toDateString()))->endOfDay();

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            return [$start, $end, $range];
        }

        $days = match ($range) {
            '7' => 7,
            '90' => 90,
            default => 30,
        };

        return [$today->copy()->subDays($days - 1)->startOfDay(), $today->copy()->endOfDay(), (string) $days];
    }

    private function siteSummaryCards(Builder $baseQuery): array
    {
        $events = (clone $baseQuery)->count();
        $uniqueVisitors = (clone $baseQuery)->distinct('visitor_key')->count('visitor_key');
        $guestEvents = (clone $baseQuery)->whereNull('user_id')->whereNull('admin_id')->count();
        $errors = (clone $baseQuery)->where('status_code', '>=', 400)->count();
        $avgDuration = (float) ((clone $baseQuery)->avg('duration_ms') ?? 0);

        return [
            $this->summaryCard('Tracked Events', $events, 'Page and app requests in range', 'fas fa-chart-line', 'primary'),
            $this->summaryCard('Unique Visitors', $uniqueVisitors, 'Estimated from hashed sessions', 'fas fa-eye', 'info'),
            $this->summaryCard('Guest Traffic', $guestEvents, 'Anonymous browsing activity', 'fas fa-users', 'secondary'),
            $this->summaryCard('Error Responses', $errors, 'HTTP 4xx and 5xx responses', 'fas fa-exclamation-triangle', $errors > 0 ? 'danger' : 'success'),
            $this->summaryCard('Avg Response', number_format($avgDuration).' ms', 'Average tracked response time', 'fas fa-stopwatch', 'dark'),
            $this->summaryCard('Bot Traffic', (clone $baseQuery)->where('is_bot', true)->count(), 'Detected crawler or preview traffic', 'fas fa-robot', 'teal'),
        ];
    }

    private function userSummaryCards(Builder $baseQuery, Carbon $start, Carbon $end): array
    {
        $signedInEvents = (clone $baseQuery)->whereNotNull('user_id')->count();
        $activeUsers = (clone $baseQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $adminEvents = (clone $baseQuery)->whereNotNull('admin_id')->count();
        $activeAdmins = (clone $baseQuery)->whereNotNull('admin_id')->distinct('admin_id')->count('admin_id');
        $newUsers = User::query()->whereBetween('created_at', [$start, $end])->count();
        $avgUserDuration = (float) ((clone $baseQuery)->whereNotNull('user_id')->avg('duration_ms') ?? 0);

        return [
            $this->summaryCard('Signed-In Events', $signedInEvents, 'Tracked user requests', 'fas fa-user-clock', 'primary'),
            $this->summaryCard('Active Users', $activeUsers, 'Users active in this range', 'fas fa-users', 'success'),
            $this->summaryCard('New Users', $newUsers, 'Registered during this period', 'fas fa-user-plus', 'warning'),
            $this->summaryCard('Admin Events', $adminEvents, number_format($activeAdmins).' active admins', 'fas fa-user-shield', 'secondary'),
            $this->summaryCard('Avg User Response', number_format($avgUserDuration).' ms', 'Signed-in request average', 'fas fa-stopwatch', 'dark'),
        ];
    }

    private function dailyTraffic(Builder $baseQuery, Carbon $start, Carbon $end): Collection
    {
        $daily = (clone $baseQuery)
            ->selectRaw('DATE(occurred_at) as day')
            ->selectRaw('COUNT(*) as events_count')
            ->selectRaw('COUNT(DISTINCT visitor_key) as visitors_count')
            ->selectRaw('COUNT(DISTINCT user_id) as users_count')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        return collect(CarbonPeriod::create($start->toDateString(), $end->toDateString()))
            ->map(function ($date) use ($daily): array {
                $key = $date->toDateString();
                $row = $daily->get($key);

                return [
                    'day' => $key,
                    'label' => $date->format('M j'),
                    'events_count' => (int) ($row->events_count ?? 0),
                    'visitors_count' => (int) ($row->visitors_count ?? 0),
                    'users_count' => (int) ($row->users_count ?? 0),
                ];
            });
    }

    private function topPages(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->select('path', 'route_name')
            ->selectRaw('COUNT(*) as events_count')
            ->selectRaw('COUNT(DISTINCT visitor_key) as visitors_count')
            ->selectRaw('AVG(duration_ms) as average_duration_ms')
            ->selectRaw('MAX(occurred_at) as last_seen_at')
            ->groupBy('path', 'route_name')
            ->orderByDesc('events_count')
            ->limit(12)
            ->get();
    }

    private function topReferrers(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->select('referrer_host')
            ->selectRaw('COUNT(*) as events_count')
            ->selectRaw('COUNT(DISTINCT visitor_key) as visitors_count')
            ->whereNotNull('referrer_host')
            ->groupBy('referrer_host')
            ->orderByDesc('events_count')
            ->limit(10)
            ->get();
    }

    private function topUsers(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->with('user:id,name,email')
            ->select('user_id')
            ->selectRaw('COUNT(*) as events_count')
            ->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as active_days')
            ->selectRaw('MAX(occurred_at) as last_seen_at')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('events_count')
            ->limit(10)
            ->get();
    }

    private function topUserPaths(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->select('path')
            ->selectRaw('COUNT(*) as events_count')
            ->selectRaw('COUNT(DISTINCT user_id) as users_count')
            ->whereNotNull('user_id')
            ->groupBy('path')
            ->orderByDesc('events_count')
            ->limit(10)
            ->get();
    }

    private function topAdmins(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->with('admin:id,name,email,role')
            ->select('admin_id')
            ->selectRaw('COUNT(*) as events_count')
            ->selectRaw('MAX(occurred_at) as last_seen_at')
            ->whereNotNull('admin_id')
            ->groupBy('admin_id')
            ->orderByDesc('events_count')
            ->limit(8)
            ->get();
    }

    private function deviceBreakdown(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->selectRaw("COALESCE(device_type, 'unknown') as device_type")
            ->selectRaw('COUNT(*) as events_count')
            ->groupBy('device_type')
            ->orderByDesc('events_count')
            ->get();
    }

    private function statusBreakdown(Builder $baseQuery): array
    {
        return [
            'success' => (clone $baseQuery)->whereBetween('status_code', [200, 299])->count(),
            'redirects' => (clone $baseQuery)->whereBetween('status_code', [300, 399])->count(),
            'client_errors' => (clone $baseQuery)->whereBetween('status_code', [400, 499])->count(),
            'server_errors' => (clone $baseQuery)->where('status_code', '>=', 500)->count(),
        ];
    }

    private function actorBreakdown(Builder $baseQuery): array
    {
        return [
            'guests' => (clone $baseQuery)->whereNull('user_id')->whereNull('admin_id')->count(),
            'users' => (clone $baseQuery)->whereNotNull('user_id')->count(),
            'admins' => (clone $baseQuery)->whereNotNull('admin_id')->count(),
        ];
    }

    private function userDailyActivity(Builder $baseQuery, Carbon $start, Carbon $end): Collection
    {
        $daily = (clone $baseQuery)
            ->selectRaw('DATE(occurred_at) as day')
            ->selectRaw('SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) as user_events_count')
            ->selectRaw('COUNT(DISTINCT user_id) as active_users_count')
            ->selectRaw('SUM(CASE WHEN admin_id IS NOT NULL THEN 1 ELSE 0 END) as admin_events_count')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        return collect(CarbonPeriod::create($start->toDateString(), $end->toDateString()))
            ->map(function ($date) use ($daily): array {
                $key = $date->toDateString();
                $row = $daily->get($key);

                return [
                    'day' => $key,
                    'label' => $date->format('M j'),
                    'user_events_count' => (int) ($row->user_events_count ?? 0),
                    'active_users_count' => (int) ($row->active_users_count ?? 0),
                    'admin_events_count' => (int) ($row->admin_events_count ?? 0),
                ];
            });
    }

    private function newUsersByDay(Carbon $start, Carbon $end): Collection
    {
        $daily = User::query()
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as users_count')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        return collect(CarbonPeriod::create($start->toDateString(), $end->toDateString()))
            ->map(function ($date) use ($daily): array {
                $key = $date->toDateString();
                $row = $daily->get($key);

                return [
                    'day' => $key,
                    'label' => $date->format('M j'),
                    'users_count' => (int) ($row->users_count ?? 0),
                ];
            });
    }

    private function recentUserEvents(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->with(['user:id,name,email', 'admin:id,name,email,role'])
            ->where(function (Builder $query): void {
                $query->whereNotNull('user_id')
                    ->orWhereNotNull('admin_id');
            })
            ->latest('occurred_at')
            ->limit(12)
            ->get();
    }

    private function summaryCard(
        string $label,
        int|string $value,
        string $detail,
        string $icon,
        string $theme,
    ): array {
        return [
            'label' => $label,
            'value' => is_int($value) ? number_format($value) : $value,
            'detail' => $detail,
            'icon' => $icon,
            'theme' => $theme,
        ];
    }
}
