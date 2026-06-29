<?php

namespace App\Notifications;

use App\Models\DjBattle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BattleEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DjBattle $battle,
        private readonly string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $challengerName = $this->battle->challenger?->dj_name ?? 'A DJ';
        $opponentName = $this->battle->opponent?->dj_name ?? 'another DJ';

        [$title, $message, $actionLabel] = match ($this->event) {
            'challenge_received' => [
                'Battle challenge received',
                "{$challengerName} challenged you to {$this->battle->title} for {$this->battle->stake_amount} tokens.",
                'Review Challenge',
            ],
            'challenge_accepted' => [
                'Battle challenge accepted',
                "{$opponentName} accepted {$this->battle->title}. Both DJs must confirm readiness.",
                'Open Battle',
            ],
            'challenge_extended' => [
                'Battle challenge extended',
                "{$challengerName} extended {$this->battle->title}.",
                'Review Challenge',
            ],
            'challenge_paused' => [
                'Battle challenge paused',
                "{$this->battle->title} paused because the response window ended.",
                'Open Battle',
            ],
            'challenge_declined' => [
                'Battle challenge declined',
                "{$opponentName} declined {$this->battle->title}.",
                'View Battles',
            ],
            'participant_ready' => [
                'DJ is ready',
                "{$this->battle->title} is waiting for both DJs to be ready.",
                'Open Battle',
            ],
            'battle_started' => [
                'Battle started',
                "{$this->battle->title} has started. The recording window is open.",
                'Open Battle',
            ],
            'battle_cancelled' => [
                'Battle cancelled',
                "{$this->battle->title} was cancelled.",
                'View Battles',
            ],
            default => [
                'Battle update',
                "{$this->battle->title} has a new update.",
                'Open Battle',
            ],
        };

        return [
            'title' => $title,
            'message' => $message,
            'category' => 'battles',
            'action_label' => $actionLabel,
            'action_url' => "/battles/{$this->battle->uuid}",
            'icon' => 'swords',
            'battle_uuid' => $this->battle->uuid,
            'battle_status' => $this->battle->status,
            'battle_stake_amount' => (int) $this->battle->stake_amount,
            'battle_voting_duration_hours' => (int) $this->battle->voting_duration_hours,
            'battle_rules' => $this->battle->rules,
            'battle_message' => $this->battle->challenge_message,
            'response_due_at' => optional($this->battle->response_due_at)->toISOString(),
            'ready_due_at' => optional($this->battle->ready_due_at)->toISOString(),
        ];
    }
}
