<?php

namespace App\Services;

use App\Models\GamificationAction;
use App\Models\GamificationEvent;
use App\Models\UserGamificationStat;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    public function awardDailyLogin(int $userId): bool
    {
        return $this->award(
            userId: $userId,
            actionKey: 'daily_login',
            targetType: 'daily_login',
            targetId: (int) now()->format('Ymd'),
            metadata: [
                'login_date' => now()->toDateString(),
            ],
        );
    }

    public function award(
        int $userId,
        string $actionKey,
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = [],
    ): bool {
        $action = GamificationAction::query()
            ->where('action_key', $actionKey)
            ->where('is_active', true)
            ->first();

        if (! $action) {
            return false;
        }

        $eventHash = $this->generateEventHash(
            $userId,
            $actionKey,
            $targetType,
            $targetId,
        );

        if (GamificationEvent::where('event_hash', $eventHash)->exists()) {
            return false;
        }

        return DB::transaction(function () use (
            $userId,
            $action,
            $targetType,
            $targetId,
            $eventHash,
            $metadata,
        ): bool {
            $event = GamificationEvent::create([
                'user_id' => $userId,
                'action_key' => $action->action_key,
                'role_context' => $action->role_context === 'both'
                    ? 'fan'
                    : $action->role_context,
                'xp_awarded' => $action->xp_amount,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'event_hash' => $eventHash,
                'metadata' => $metadata,
            ]);

            $stats = UserGamificationStat::firstOrCreate(
                ['user_id' => $userId],
                [
                    'dj_xp' => 0,
                    'fan_xp' => 0,
                    'total_xp' => 0,
                    'dj_level' => 1,
                    'fan_level' => 1,
                    'total_level' => 1,
                ],
            );

            if ($event->role_context === 'dj') {
                $stats->dj_xp += $event->xp_awarded;
            }

            if ($event->role_context === 'fan') {
                $stats->fan_xp += $event->xp_awarded;
            }

            $stats->total_xp = $stats->dj_xp + $stats->fan_xp;
            $stats->dj_level = $this->calculateLevel($stats->dj_xp);
            $stats->fan_level = $this->calculateLevel($stats->fan_xp);
            $stats->total_level = $this->calculateLevel($stats->total_xp);
            $stats->dj_rank = $this->calculateDjRank($stats->dj_level);
            $stats->fan_rank = $this->calculateFanRank($stats->fan_level);
            $stats->last_activity_at = now();
            $stats->save();

            return true;
        });
    }

    protected function generateEventHash(
        int $userId,
        string $actionKey,
        ?string $targetType,
        ?int $targetId,
    ): string {
        return hash('sha256', implode('|', [
            $userId,
            $actionKey,
            $targetType ?? 'none',
            $targetId ?? 'none',
        ]));
    }

    protected function calculateLevel(int $xp): int
    {
        return match (true) {
            $xp >= 10000 => 10,
            $xp >= 7500 => 9,
            $xp >= 5000 => 8,
            $xp >= 3000 => 7,
            $xp >= 2000 => 6,
            $xp >= 1000 => 5,
            $xp >= 500 => 4,
            $xp >= 250 => 3,
            $xp >= 100 => 2,
            default => 1,
        };
    }

    protected function calculateDjRank(int $level): string
    {
        return match (true) {
            $level >= 7 => 'Legend',
            $level >= 6 => 'Headliner',
            $level >= 5 => 'Battle DJ',
            $level >= 4 => 'Club DJ',
            $level >= 3 => 'Local DJ',
            $level >= 2 => 'Bedroom DJ',
            default => 'New DJ',
        };
    }

    protected function calculateFanRank(int $level): string
    {
        return match (true) {
            $level >= 6 => 'Legendary Fan',
            $level >= 5 => 'Crowd Captain',
            $level >= 4 => 'Taste Maker',
            $level >= 3 => 'Super Fan',
            $level >= 2 => 'Supporter',
            default => 'Listener',
        };
    }
}
