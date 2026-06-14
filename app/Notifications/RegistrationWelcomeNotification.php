<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RegistrationWelcomeNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to BlendBeats',
            'message' => 'Your account is ready. Start by completing your profile, creating a DJ profile, or uploading your first mix.',
            'category' => 'account',
            'action_label' => 'Go To Dashboard',
            'action_url' => '/dashboard',
            'icon' => 'bell',
        ];
    }
}
