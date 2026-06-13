<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create([
            ...$attributes,
            'media_storage_tier' => config('billing.subscription.free_tier', 'free'),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $this->userPayload(Auth::guard('web')->user()),
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload(Auth::guard('web')->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'If an account exists for that email, password reset instructions will be sent.',
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'avatar' => ['nullable', 'image', 'max:2048'],
            'avatar_url' => ['nullable', 'url', 'max:255'],
            'remove_avatar' => ['nullable', 'boolean'],
            'is_gravatar' => ['nullable', 'boolean'],
            'use_gravatar' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $isGravatar = $request->has('is_gravatar')
            ? $request->boolean('is_gravatar')
            : $request->boolean('use_gravatar');

        $user->forceFill([
            'is_gravatar' => $isGravatar,
            'use_gravatar' => $isGravatar,
        ])->save();

        if ($request->boolean('remove_avatar')) {
            $user->removeAvatar();
        } elseif ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $fileName = 'avatar-'.$user->id.'-'.Str::random(12).'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('media/accounts/avatar', $fileName, 'public');

            $user->setAvatarFromFile($path);
        } elseif (! empty($attributes['avatar_url'])) {
            $user->setAvatarFromUrl($attributes['avatar_url']);
        }

        return response()->json([
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    private function userPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'avatar_url' => $user->getAvatarUrl(),
            'gravatar_url' => $user->getGravatarUrl(),
            'custom_avatar_url' => $user->getUploadedAvatarUrl(),
            'generated_avatar_url' => $user->getGeneratedAvatarUrl(),
            'avatar_source' => $user->avatar_source,
            'is_gravatar' => $user->is_gravatar,
            'use_gravatar' => $user->use_gravatar,
            'media_storage_tier' => $user->media_storage_tier,
            'profile' => $this->profilePayload($user),
            'dj_profile' => $this->djProfilePayload($user),
        ];
    }

    private function profilePayload(User $user): ?array
    {
        if (! Schema::hasTable('profiles')) {
            return null;
        }

        $profile = DB::table('profiles')->where('user_id', $user->id)->first();

        return $profile ? (array) $profile : null;
    }

    private function djProfilePayload(User $user): ?array
    {
        if (! Schema::hasTable('dj_profiles')) {
            return null;
        }

        $profile = DB::table('dj_profiles')
            ->where('user_id', $user->id)
            ->first(['id', 'dj_name', 'handle', 'profile_status', 'visibility']);

        return $profile ? [
            'id' => $profile->id,
            'dj_name' => $profile->dj_name,
            'handle' => $profile->handle,
            'profile_status' => $profile->profile_status,
            'visibility' => $profile->visibility,
        ] : null;
    }
}
