<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GamificationEvent;
use App\Models\UserBadge;
use App\Models\UserGamificationStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountGamificationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $stats = UserGamificationStat::query()
            ->where('user_id', $request->user()->id)
            ->first();

        $badges = UserBadge::query()
            ->with('badge:id,badge_key,name,description,icon,rarity')
            ->where('user_id', $request->user()->id)
            ->latest('unlocked_at')
            ->latest('id')
            ->get();

        return response()->json([
            'dj_xp' => (int) ($stats?->dj_xp ?? 0),
            'fan_xp' => (int) ($stats?->fan_xp ?? 0),
            'total_xp' => (int) ($stats?->total_xp ?? 0),
            'dj_level' => (int) ($stats?->dj_level ?? 1),
            'fan_level' => (int) ($stats?->fan_level ?? 1),
            'total_level' => (int) ($stats?->total_level ?? 1),
            'dj_rank' => $stats?->dj_rank,
            'fan_rank' => $stats?->fan_rank,
            'last_activity_at' => $stats?->last_activity_at?->toISOString(),
            'badges' => $badges->map(fn (UserBadge $userBadge): array => [
                'badge_key' => $userBadge->badge?->badge_key,
                'name' => $userBadge->badge?->name,
                'description' => $userBadge->badge?->description,
                'icon' => $userBadge->badge?->icon,
                'rarity' => $userBadge->badge?->rarity,
                'unlocked_at' => $userBadge->unlocked_at?->toISOString(),
            ])->values(),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $events = GamificationEvent::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json($events->map(fn (GamificationEvent $event): array => [
            'action_key' => $event->action_key,
            'xp_awarded' => (int) $event->xp_awarded,
            'role_context' => $event->role_context,
            'metadata' => $event->metadata ?? [],
            'created_at' => $event->created_at?->toISOString(),
        ])->values());
    }
}
