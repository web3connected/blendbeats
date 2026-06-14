<?php

namespace App\Notifications;

use App\Models\MediaFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PortfolioAudioUploadedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly MediaFile $file) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $portfolio = $this->file->metadata['portfolio'] ?? [];
        $title = $portfolio['title'] ?? $this->file->original_name ?? $this->file->name;
        $visibility = $portfolio['visibility'] ?? 'draft';

        return [
            'title' => 'Audio uploaded',
            'message' => "{$title} was added to your DJ portfolio as {$visibility}.",
            'category' => 'uploads',
            'action_label' => 'Open Portfolio',
            'action_url' => '/dj/portfolio',
            'icon' => 'music',
        ];
    }
}
