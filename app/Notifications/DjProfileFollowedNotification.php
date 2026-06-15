<?php

namespace App\Notifications;

use App\Models\DjProfile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DjProfileFollowedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DjProfile $profile,
        private readonly User $follower,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New DJ follower',
            'message' => "{$this->follower->name} followed {$this->profile->dj_name}.",
            'category' => 'dj_profile',
            'action_label' => 'View DJ Profile',
            'action_url' => "/djs/{$this->profile->handle}",
            'icon' => 'users',
        ];
    }
}
