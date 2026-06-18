<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class DjLoungePostReportedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $reportId,
        public readonly int $postId,
        public readonly int $postAuthorUserId,
        public readonly User $reporter,
        public readonly string $reason,
        public readonly ?string $details,
        public readonly string $postBody,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('DJ Lounge post reported')
            ->greeting('DJ Lounge Report')
            ->line("Report ID: {$this->reportId}")
            ->line("Post ID: {$this->postId}")
            ->line("Post author user ID: {$this->postAuthorUserId}")
            ->line("Reporter: {$this->reporter->name} ({$this->reporter->email})")
            ->line('Reason: '.str_replace('_', ' ', $this->reason))
            ->line('Details: '.($this->details ?: 'No extra details provided.'))
            ->line('Post excerpt: '.Str::limit($this->postBody, 500))
            ->action('Open DJ Lounge', url('/dj-lounge'));
    }
}
