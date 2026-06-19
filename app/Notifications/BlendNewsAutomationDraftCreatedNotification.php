<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BlendNewsAutomationDraftCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Post $post) {}

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
            'title' => 'Automation draft ready for review',
            'message' => "BlendNews draft created: {$this->post->title}",
            'category' => 'system',
            'action_label' => 'Review Draft',
            'action_url' => route('admin.blendnews.edit', $this->post, false),
            'icon' => 'newspaper',
            'post_id' => $this->post->id,
            'status' => $this->post->status,
        ];
    }
}
