<?php

namespace App\Http\Middleware;

use App\Models\SiteActivityEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackSiteActivity
{
    private static ?bool $activityTableExists = null;

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $this->record($request, $response, $startedAt);

        return $response;
    }

    private function record(Request $request, Response $response, float $startedAt): void
    {
        if (! $this->shouldTrack($request)) {
            return;
        }

        try {
            self::$activityTableExists ??= Schema::hasTable('site_activity_events');

            if (! self::$activityTableExists) {
                return;
            }

            $sessionId = $request->hasSession() ? $request->session()->getId() : null;
            $userAgent = $request->userAgent();
            $referrer = $request->headers->get('referer');

            SiteActivityEvent::query()->create([
                'occurred_at' => now(),
                'user_id' => auth('web')->id(),
                'admin_id' => auth('admin')->id(),
                'visitor_key' => $this->hashValue($sessionId ?: $request->ip().'|'.$userAgent),
                'session_id_hash' => $this->hashValue($sessionId),
                'ip_hash' => $this->hashValue($request->ip()),
                'method' => $request->method(),
                'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 512),
                'route_name' => $request->route()?->getName(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => max(0, (int) round((microtime(true) - $startedAt) * 1000)),
                'referrer_host' => $this->referrerHost($referrer),
                'referrer_url' => $referrer ? mb_substr($referrer, 0, 2048) : null,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
                'device_type' => $this->deviceType($userAgent),
                'is_bot' => $this->isBot($userAgent),
                'is_ajax' => $request->ajax(),
            ]);
        } catch (Throwable $exception) {
            Log::debug('Unable to record site activity event.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldTrack(Request $request): bool
    {
        if ($request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return false;
        }

        return ! $request->is(
            'build/*',
            'css/*',
            'js/*',
            'images/*',
            'img/*',
            'fonts/*',
            'vendor/*',
            'storage/*',
            'favicon.ico',
            'robots.txt',
            'up',
            '_debugbar/*'
        );
    }

    private function hashValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function referrerHost(?string $referrer): ?string
    {
        if (! $referrer) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        return is_string($host) ? mb_substr($host, 0, 255) : null;
    }

    private function deviceType(?string $userAgent): string
    {
        $agent = strtolower($userAgent ?? '');

        if (str_contains($agent, 'tablet') || str_contains($agent, 'ipad')) {
            return 'tablet';
        }

        if (str_contains($agent, 'mobile') || str_contains($agent, 'android') || str_contains($agent, 'iphone')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function isBot(?string $userAgent): bool
    {
        return (bool) preg_match('/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|whatsapp|preview/i', $userAgent ?? '');
    }
}
