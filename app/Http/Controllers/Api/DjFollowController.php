<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DjProfile;
use App\Models\Follower;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DjFollowController extends Controller
{
    public function store(Request $request, string $handle): JsonResponse
    {
        $profile = $this->publicProfile($handle);
        $user = $request->user();

        abort_if((int) $profile->user_id === (int) $user->id, 422, 'You cannot follow your own DJ profile.');

        Follower::query()->firstOrCreate([
            'follower_user_id' => $user->id,
            'followed_dj_id' => $profile->id,
        ]);

        return response()->json($this->payload($profile, true));
    }

    public function destroy(Request $request, string $handle): JsonResponse
    {
        $profile = $this->publicProfile($handle);
        $user = $request->user();

        Follower::query()
            ->where('follower_user_id', $user->id)
            ->where('followed_dj_id', $profile->id)
            ->delete();

        return response()->json($this->payload($profile, false));
    }

    private function publicProfile(string $handle): DjProfile
    {
        return DjProfile::query()
            ->where('handle', $handle)
            ->where('visibility', 'public')
            ->where('profile_status', 'active')
            ->firstOrFail();
    }

    private function payload(DjProfile $profile, bool $isFollowing): array
    {
        return [
            'is_following' => $isFollowing,
            'followers_count' => $profile->followers()->count(),
        ];
    }
}
