<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use Illuminate\Http\JsonResponse;

class GamificationBadgeController extends Controller
{
    public function index(): JsonResponse
    {
        $badges = Badge::query()
            ->where('is_active', true)
            ->orderBy('role_context')
            ->orderBy('unlock_threshold')
            ->orderBy('name')
            ->get();

        return response()->json($badges->map(fn (Badge $badge): array => [
            'badge_key' => $badge->badge_key,
            'name' => $badge->name,
            'description' => $badge->description,
            'role_context' => $badge->role_context,
            'icon' => $badge->icon,
            'rarity' => $badge->rarity,
            'unlock_action_key' => $badge->unlock_action_key,
            'unlock_threshold' => (int) $badge->unlock_threshold,
            'unlock_condition' => $this->unlockConditionFor($badge),
        ])->values());
    }

    private function unlockConditionFor(Badge $badge): string
    {
        return match ($badge->badge_key) {
            'first_portfolio_upload' => 'Upload first portfolio item',
            'first_scratch_upload' => 'Upload first scratch routine',
            'battle_voter' => 'Cast first battle vote',
            'fan_favorite' => 'Reach DJ engagement milestone',
            'weekly_grinder' => 'Complete weekly activity streak',
            'super_fan' => 'Reach fan engagement milestone',
            default => $badge->description ?? 'Complete the badge requirement',
        };
    }
}
