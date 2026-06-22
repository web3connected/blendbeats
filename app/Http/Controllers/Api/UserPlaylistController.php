<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mix;
use App\Models\User;
use App\Models\UserPlaylistItem;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserPlaylistController extends Controller
{
    public function index(): JsonResponse
    {
        $user = $this->user();

        $items = UserPlaylistItem::query()
            ->with(['mix.user:id,name', 'mix.audioMediaFile', 'mix.coverMediaFile'])
            ->where('user_id', $user->id)
            ->whereHas('mix', fn ($query) => $query->public())
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json([
            'playlist' => $items->map(fn (UserPlaylistItem $item): array => $this->itemPayload($item))->values(),
        ]);
    }

    public function store(Mix $mix): JsonResponse
    {
        abort_unless($mix->is_public && $mix->published_at && $mix->audio_url, 404);

        $user = $this->user();
        $position = (int) UserPlaylistItem::query()
            ->where('user_id', $user->id)
            ->max('position');

        $item = UserPlaylistItem::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'mix_id' => $mix->id,
            ],
            [
                'position' => $position + 1,
                'added_at' => now(),
            ],
        );

        if ($item->wasRecentlyCreated) {
            app(GamificationService::class)->award(
                userId: $user->id,
                actionKey: 'mix_saved_to_playlist',
                targetType: 'mix',
                targetId: $mix->id,
                metadata: [
                    'playlist_item_id' => $item->id,
                    'mix_title' => $mix->title ?? null,
                ],
            );
        }

        $item->load(['mix.user:id,name', 'mix.audioMediaFile', 'mix.coverMediaFile']);

        return response()->json([
            'item' => $this->itemPayload($item),
        ], $item->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Mix $mix): JsonResponse
    {
        $user = $this->user();

        UserPlaylistItem::query()
            ->where('user_id', $user->id)
            ->where('mix_id', $mix->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        return $user;
    }

    private function itemPayload(UserPlaylistItem $item): array
    {
        $mix = $item->mix;

        return [
            'id' => $item->id,
            'added_at' => $item->added_at?->toISOString(),
            'mix' => [
                'id' => $mix->id,
                'title' => $mix->title,
                'slug' => $mix->slug,
                'description' => $mix->description,
                'genre' => $mix->genre,
                'audio_url' => $mix->audio_url,
                'cover_image_url' => $mix->cover_image_url,
                'duration' => $mix->duration,
                'is_featured' => $mix->is_featured,
                'play_count' => $mix->play_count,
                'rating_average' => (float) $mix->rating_average,
                'rating_count' => $mix->rating_count,
                'published_at' => $mix->published_at?->toISOString(),
                'created_at' => $mix->created_at?->toISOString(),
                'dj' => [
                    'id' => $mix->user?->id,
                    'name' => $mix->dj_name,
                ],
            ],
        ];
    }
}
