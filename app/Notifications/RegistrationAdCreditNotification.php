<?php

namespace App\Notifications;

use App\Models\UserAdCredit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RegistrationAdCreditNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly UserAdCredit $credit) {}

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
            'title' => 'Free featured ad added',
            'message' => 'You received a signup bonus: one free 1-day featured ad campaign.',
            'category' => 'ads',
            'action_label' => 'Use Free Ad',
            'action_url' => '/account/featured-ads/placements',
            'icon' => 'gift',
            'credit_id' => $this->credit->id,
            'credit_type' => $this->credit->credit_type,
            'duration_days' => $this->credit->duration_days,
        ];
    }
}
