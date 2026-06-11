<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\AdminRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function __construct(private readonly AdminRoleService $roleService) {}

    public function index(): View
    {
        return view('admin.admin-users.index', [
            'admins' => Admin::query()
                ->with('roles')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.admin-users.create', [
            'roles' => $this->roleService->orderedRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'email_verified_at' => ['nullable', 'date'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('guard_name', 'admin'))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = Role::query()
            ->where('guard_name', 'admin')
            ->findOrFail($validated['role_id']);

        $admin = Admin::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $role->name,
            'is_active' => $request->boolean('is_active'),
        ]);

        $admin->syncRoles([$role]);

        return redirect()
            ->route('admin.admincenter.adminusers.show', $admin)
            ->with('status', 'Admin user created.');
    }

    public function show(Admin $adminuser): View
    {
        $adminuser->load('roles.permissions');

        return view('admin.admin-users.show', [
            'adminUser' => $adminuser,
            'currentRole' => $this->currentRole($adminuser),
        ]);
    }

    public function edit(Admin $adminuser): View
    {
        $adminuser->load('roles');

        return view('admin.admin-users.edit', [
            'adminUser' => $adminuser,
            'roles' => $this->roleService->orderedRoles(),
            'currentRole' => $this->currentRole($adminuser),
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
        $this->authorizePermission('adminusers.update');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($adminUser->id),
            ],
            'email_verified_at' => ['nullable', 'date'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('guard_name', 'admin'))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = Role::query()
            ->where('guard_name', 'admin')
            ->findOrFail($validated['role_id']);

        $adminUser->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified_at'] ?? null,
            'role' => $role->name,
            'is_active' => $request->boolean('is_active'),
        ]);

        $adminUser->syncRoles([$role]);

        return redirect()
            ->to($this->editTabUrl($adminUser, 'profile-info'))
            ->with('status', 'Profile info updated.')
            ->with('status_tab', 'profile');
    }

    private function updatePassword(Request $request, Admin $adminUser): RedirectResponse
    {
        $this->authorizePermission('adminusers.reset-password');

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
        $this->authorizePermission('adminusers.manage-avatar');

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

    private function currentRole(Admin $adminUser): ?Role
    {
        return $adminUser->roles->first()
            ?? Role::query()
                ->where('guard_name', 'admin')
                ->where('name', $adminUser->role)
                ->first();
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth('admin')->user()?->can($permission), 403);
    }
}
