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
        [$start, $end, $range] = $this->dateRange($request);

        $baseQuery = SiteActivityEvent::query()
            ->whereBetween('occurred_at', [$start, $end]);

        return view('admin.site-analytics.index', [
            'range' => $range,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'generatedAt' => now(),
            'summaryCards' => $this->summaryCards($baseQuery, $start, $end),
            'dailyTraffic' => $this->dailyTraffic($baseQuery, $start, $end),
            'topPages' => $this->topPages($baseQuery),
            'topReferrers' => $this->topReferrers($baseQuery),
            'topUsers' => $this->topUsers($baseQuery),
            'topAdmins' => $this->topAdmins($baseQuery),
            'deviceBreakdown' => $this->deviceBreakdown($baseQuery),
            'statusBreakdown' => $this->statusBreakdown($baseQuery),
            'recentEvents' => $this->recentEvents($baseQuery),
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

    private function summaryCards(Builder $baseQuery, Carbon $start, Carbon $end): array
    {
        $events = (clone $baseQuery)->count();
        $uniqueVisitors = (clone $baseQuery)->distinct('visitor_key')->count('visitor_key');
        $signedInEvents = (clone $baseQuery)->whereNotNull('user_id')->count();
        $activeUsers = (clone $baseQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $adminEvents = (clone $baseQuery)->whereNotNull('admin_id')->count();
        $errors = (clone $baseQuery)->where('status_code', '>=', 400)->count();
        $avgDuration = (float) ((clone $baseQuery)->avg('duration_ms') ?? 0);
        $newUsers = User::query()->whereBetween('created_at', [$start, $end])->count();

        return [
            $this->summaryCard('Tracked Events', $events, 'Page and app requests in range', 'fas fa-chart-line', 'primary'),
            $this->summaryCard('Unique Visitors', $uniqueVisitors, 'Estimated from hashed sessions', 'fas fa-eye', 'info'),
            $this->summaryCard('Active Users', $activeUsers, number_format($signedInEvents).' signed-in events', 'fas fa-user-clock', 'success'),
            $this->summaryCard('New Users', $newUsers, 'Registered during this period', 'fas fa-user-plus', 'warning'),
            $this->summaryCard('Admin Events', $adminEvents, 'Admin Center activity', 'fas fa-user-shield', 'secondary'),
            $this->summaryCard('Error Responses', $errors, 'HTTP 4xx and 5xx responses', 'fas fa-exclamation-triangle', $errors > 0 ? 'danger' : 'success'),
            $this->summaryCard('Avg Response', number_format($avgDuration).' ms', 'Average tracked response time', 'fas fa-stopwatch', 'dark'),
            $this->summaryCard('Bot Traffic', (clone $baseQuery)->where('is_bot', true)->count(), 'Detected crawler or preview traffic', 'fas fa-robot', 'teal'),
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

    private function recentEvents(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->with(['user:id,name,email', 'admin:id,name,email,role'])
            ->latest('occurred_at')
            ->limit(25)
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
