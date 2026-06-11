<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.admin-users.index', [
            'admins' => Admin::query()->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.admin-users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'email_verified_at' => ['nullable', 'date'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $admin = Admin::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.admincenter.adminusers.show', $admin)
            ->with('status', 'Admin user created.');
    }

    public function show(Admin $adminuser): View
    {
        return view('admin.admin-users.show', [
            'adminUser' => $adminuser,
        ]);
    }

    public function edit(Admin $adminuser): View
    {
        return view('admin.admin-users.edit', [
            'adminUser' => $adminuser,
        ]);
    }

    public function update(Request $request, Admin $adminuser): RedirectResponse
    {
        return match ($request->input('_section', 'profile')) {
            'password' => $this->updatePassword($request, $adminuser),
            'avatar' => $this->updateAvatar($request, $adminuser),
            default => $this->updateProfile($request, $adminuser),
        };
    }

    public function destroy(Admin $adminuser): RedirectResponse
    {
        if (auth('admin')->id() === $adminuser->id) {
            return back()->withErrors(['adminuser' => 'You cannot delete your own admin account.']);
        }

        $adminuser->delete();

        return redirect()
            ->route('admin.admincenter.adminusers.index')
            ->with('status', 'Admin user deleted.');
    }

    private function updateProfile(Request $request, Admin $adminUser): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($adminUser->id),
            ],
            'email_verified_at' => ['nullable', 'date'],
            'role' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $adminUser->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->to($this->editTabUrl($adminUser, 'profile-info'))
            ->with('status', 'Profile info updated.')
            ->with('status_tab', 'profile');
    }

    private function updatePassword(Request $request, Admin $adminUser): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'new_password' => ['required', 'confirmed', 'min:8'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to($this->editTabUrl($adminUser, 'password-reset'))
                ->withErrors($validator)
                ->withInput($request->except(['new_password', 'new_password_confirmation']));
        }

        $adminUser->forceFill([
            'password' => Hash::make($validator->validated()['new_password']),
        ])->save();

        return redirect()
            ->to($this->editTabUrl($adminUser, 'password-reset'))
            ->with('status', 'Password reset.')
            ->with('status_tab', 'password');
    }

    private function updateAvatar(Request $request, Admin $adminUser): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => ['nullable', 'image', 'max:2048'],
            'use_gravatar' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to($this->editTabUrl($adminUser, 'avatar-upload'))
                ->withErrors($validator)
                ->withInput($request->except('avatar'));
        }

        $updates = [];

        if ($request->hasFile('avatar')) {
            if (! Schema::hasColumn($adminUser->getTable(), 'avatar')) {
                return redirect()
                    ->to($this->editTabUrl($adminUser, 'avatar-upload'))
                    ->withErrors(['avatar' => 'This account table does not have an avatar field.'])
                    ->withInput($request->except('avatar'));
            }

            $updates['avatar'] = $request->file('avatar')->store("accounts/admins/{$adminUser->id}/avatar", 'public');
        }

        if (Schema::hasColumn($adminUser->getTable(), 'use_gravatar')) {
            $updates['use_gravatar'] = $request->boolean('use_gravatar');
        }

        if ($updates !== []) {
            $adminUser->update($updates);
        }

        return redirect()
            ->to($this->editTabUrl($adminUser, 'avatar-upload'))
            ->with('status', 'Avatar updated.')
            ->with('status_tab', 'avatar');
    }

    private function editTabUrl(Admin $adminUser, string $tab): string
    {
        return route('admin.admincenter.adminusers.edit', $adminUser).'#'.$tab;
    }
}
