<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $user = User::create($attributes);

        Auth::guard('web')->login($user);

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

        return response()->json([
            'ok' => true,
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
        $isGravatar = $request->has('is_gravatar')
            ? $request->boolean('is_gravatar')
            : $request->boolean('use_gravatar');

        $user->is_gravatar = $isGravatar;
        $user->use_gravatar = $isGravatar;
        $user->save();

        if ($request->boolean('remove_avatar')) {
            $user->removeAvatar();
        } elseif ($request->hasFile('avatar')) {
            $directory = public_path('media/accounts/avatars');

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $file = $request->file('avatar');
            $fileName = 'user-'.$user->id.'-'.Str::random(12).'.'.$file->getClientOriginalExtension();

            $file->move($directory, $fileName);
            $user->setAvatarFromFile('accounts/avatars/'.$fileName);
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

        $user->load(['profile', 'djProfile:id,user_id,dj_name,handle,profile_status,visibility']);
        $djProfile = $user->djProfile;

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
            'profile' => $user->profile,
            'dj_profile' => $djProfile ? [
                'id' => $djProfile->id,
                'dj_name' => $djProfile->dj_name,
                'handle' => $djProfile->handle,
                'profile_status' => $djProfile->profile_status,
                'visibility' => $djProfile->visibility,
            ] : null,
        ];
    }
}
