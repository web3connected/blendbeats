<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    private const CATEGORIES = [
        'account',
        'dj_profile',
        'uploads',
        'mixes',
        'dj_lounge',
        'featured_ads',
        'billing',
        'support',
        'system',
    ];

    public function index(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'status' => ['nullable', 'in:all,read,unread'],
            'category' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $attributes['status'] ?? 'all';
        $category = $attributes['category'] ?? null;
        $limit = (int) ($attributes['limit'] ?? 50);

        $query = $request->user()
            ->notifications()
            ->latest();

        if ($status === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($status === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query
            ->limit($limit)
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => ! $category || ($notification->data['category'] ?? null) === $category)
            ->values();

        return response()->json([
            'notifications' => $notifications->map(fn (DatabaseNotification $notification): array => $this->payload($notification))->all(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'filters' => [
                'categories' => self::CATEGORIES,
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $this->notificationForUser($request, $notificationId);
        $notification->markAsRead();

        return response()->json([
            'notification' => $this->payload($notification->refresh()),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }

    public function destroy(Request $request, string $notificationId): JsonResponse
    {
        $notification = $this->notificationForUser($request, $notificationId);
        $notification->delete();

        return response()->json([
            'deleted' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    private function notificationForUser(Request $request, string $notificationId): DatabaseNotification
    {
        return $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();
    }

    private function payload(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? '',
            'category' => $data['category'] ?? 'system',
            'action_label' => $data['action_label'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'icon' => $data['icon'] ?? 'bell',
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
