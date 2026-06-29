<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AffiliateAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAffiliateAnalyticsController extends Controller
{
    public function __invoke(Request $request, AffiliateAnalyticsService $analytics): JsonResponse
    {
        $validated = $request->validate([
            'leaderboard_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($analytics->report((int) ($validated['leaderboard_limit'] ?? 10)));
    }
}
