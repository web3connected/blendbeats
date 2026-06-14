<?php

namespace App\Notifications;

use App\Models\DjProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DjProfileCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly DjProfile $profile) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'DJ profile created',
            'message' => "{$this->profile->dj_name} is now set up as your BlendBeats DJ profile.",
            'category' => 'dj_profile',
            'action_label' => 'View DJ Profile',
            'action_url' => $this->profile->visibility === 'public' ? "/djs/{$this->profile->handle}" : '/dj/edit',
            'icon' => 'radio',
        ];
    }
}
