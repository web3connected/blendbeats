<?php

namespace App\Http\Middleware;

use App\Services\AffiliateReferralTrackingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAffiliateReferral
{
    public function __construct(private readonly AffiliateReferralTrackingService $referrals)
    {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldCapture($request)) {
            $this->referrals->capture($request);
        }

        return $next($request);
    }

    private function shouldCapture(Request $request): bool
    {
        if (! $request->query->has(AffiliateReferralTrackingService::QUERY_PARAMETER)) {
            return false;
        }

        return ! $request->is('admin', 'admin/*', 'api', 'api/*', 'automation', 'automation/*');
    }
}
