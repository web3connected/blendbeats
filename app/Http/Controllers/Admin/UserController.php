<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'email_verified_at' => ['nullable', 'date'],
            'media_storage_tier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
            'media_storage_tier' => $validated['media_storage_tier'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'User created.');
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        return match ($request->input('_section', 'profile')) {
            'password' => $this->updatePassword($request, $user),
            'avatar' => $this->updateAvatar($request, $user),
            default => $this->updateProfile($request, $user),
        };
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User deleted.');
    }

    private function updateProfile(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'email_verified_at' => ['nullable', 'date'],
            'media_storage_tier' => ['required', 'string', 'max:255'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
            'media_storage_tier' => $validated['media_storage_tier'],
        ]);

        return redirect()
            ->to($this->editTabUrl($user, 'profile-info'))
            ->with('status', 'Profile info updated.')
            ->with('status_tab', 'profile');
    }

    private function updatePassword(Request $request, User $user): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'new_password' => ['required', 'confirmed', 'min:8'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to($this->editTabUrl($user, 'password-reset'))
                ->withErrors($validator)
                ->withInput($request->except(['new_password', 'new_password_confirmation']));
        }

        $user->forceFill([
            'password' => Hash::make($validator->validated()['new_password']),
        ])->save();

        return redirect()
            ->to($this->editTabUrl($user, 'password-reset'))
            ->with('status', 'Password reset.')
            ->with('status_tab', 'password');
    }

    private function updateAvatar(Request $request, User $user): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => ['nullable', 'image', 'max:2048'],
            'use_gravatar' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to($this->editTabUrl($user, 'avatar-upload'))
                ->withErrors($validator)
                ->withInput($request->except('avatar'));
        }

        $updates = [];

        if ($request->hasFile('avatar')) {
            if (! Schema::hasColumn($user->getTable(), 'avatar')) {
                return redirect()
                    ->to($this->editTabUrl($user, 'avatar-upload'))
                    ->withErrors(['avatar' => 'This user table does not have an avatar field.'])
                    ->withInput($request->except('avatar'));
            }

            $updates['avatar'] = $request->file('avatar')->store("accounts/users/{$user->id}/avatar", 'public');
        }

        if (Schema::hasColumn($user->getTable(), 'use_gravatar')) {
            $updates['use_gravatar'] = $request->boolean('use_gravatar');
        }

        if (Schema::hasColumn($user->getTable(), 'is_gravatar')) {
            $updates['is_gravatar'] = $request->boolean('use_gravatar');
        }

        if ($updates !== []) {
            $user->update($updates);
        }

        return redirect()
            ->to($this->editTabUrl($user, 'avatar-upload'))
            ->with('status', 'Avatar updated.')
            ->with('status_tab', 'avatar');
    }

    private function editTabUrl(User $user, string $tab): string
    {
        return route('admin.users.edit', $user).'#'.$tab;
    }
}
