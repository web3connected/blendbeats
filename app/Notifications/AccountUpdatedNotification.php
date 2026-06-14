<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountUpdatedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Account updated',
            'message' => 'Your account profile details were updated successfully.',
            'category' => 'account',
            'action_label' => 'Review Profile',
            'action_url' => '/account/profile',
            'icon' => 'user',
        ];
    }
}
