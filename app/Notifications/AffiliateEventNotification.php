<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AffiliateEventNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->payload['title'] ?? 'Affiliate update',
            'message' => $this->payload['message'] ?? '',
            'category' => 'affiliate',
            'action_label' => $this->payload['action_label'] ?? 'View Affiliate Dashboard',
            'action_url' => $this->payload['action_url'] ?? '/account/affiliate',
            'icon' => $this->payload['icon'] ?? 'gift',
            'event_type' => $this->payload['event_type'] ?? 'affiliate_event',
            ...($this->payload['data'] ?? []),
        ];
    }
}
