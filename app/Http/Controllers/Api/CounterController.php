<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DjProfile;
use App\Models\Mix;
use Illuminate\Http\JsonResponse;

class CounterController extends Controller
{
    public function increment(string $type, string $id, ?string $action = null): JsonResponse
    {
        $normalizedType = str($type)->lower()->replace('_', '-')->toString();
        $normalizedAction = str($action ?: 'view')->lower()->replace('_', '-')->toString();

        return match ($normalizedType) {
            'dj-profile', 'dj-profiles', 'dj', 'djs' => $this->incrementDjProfile($id, $normalizedAction),
            'mix', 'mixes' => $this->incrementMix($id, $normalizedAction),
            default => abort(404, 'Counter target is not supported yet.'),
        };
    }

    private function incrementDjProfile(string $id, string $action): JsonResponse
    {
        abort_unless($action === 'view', 404, 'DJ profile counter action is not supported.');

        $profile = DjProfile::query()
            ->where('handle', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        abort_unless($profile->visibility === 'public' && $profile->profile_status === 'active', 404);

        $profile->incrementViewCount();

        return response()->json([
            'type' => 'dj_profiles',
            'id' => $profile->id,
            'key' => $profile->handle,
            'action' => 'view',
            'count' => $profile->refresh()->view_count,
            'label' => 'views',
        ]);
    }

    private function incrementMix(string $id, string $action): JsonResponse
    {
        abort_unless($action === 'play', 404, 'Mix counter action is not supported.');

        $mix = Mix::query()
            ->where('slug', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        abort_unless($mix->is_public && $mix->published_at, 404);

        $mix->incrementPlayCount();

        return response()->json([
            'type' => 'mixes',
            'id' => $mix->id,
            'key' => $mix->slug,
            'action' => 'play',
            'count' => $mix->refresh()->play_count,
            'label' => 'plays',
        ]);
    }
}
