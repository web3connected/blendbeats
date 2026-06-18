<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $email,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('admin.password.reset', [
            'token' => $this->token,
            'email' => $this->email,
        ]);

        return (new MailMessage)
            ->subject('Reset your BlendBeats admin password')
            ->greeting('BlendBeats Admin')
            ->line('We received a request to reset the password for this administrator account.')
            ->action('Reset admin password', $url)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not request a password reset, no action is required.');
    }
}
