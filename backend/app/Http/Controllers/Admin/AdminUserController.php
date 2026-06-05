<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.admin-users.index', [
            'admins' => Admin::query()
                ->with('roles')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.admin-users.create', [
            'roles' => $this->roles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'role' => ['required', Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'admin'))],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $admin = Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active'),
            'password' => Hash::make($validated['password']),
        ]);

        $admin->syncRoles([$validated['role']]);

        return redirect()
            ->route('admin.admin-users.index')
            ->with('status', 'Admin user created.');
    }

    public function edit(Admin $adminUser): View
    {
        return view('admin.admin-users.edit', [
            'adminUser' => $adminUser,
            'roles' => $this->roles(),
        ]);
    }

    public function update(Request $request, Admin $adminUser): RedirectResponse
    {
        return match ($request->input('form_section', 'details')) {
            'password' => $this->updatePassword($request, $adminUser),
            'avatar' => $this->updateAvatar($request, $adminUser),
            default => $this->updateDetails($request, $adminUser),
        };
    }

    public function destroy(Admin $adminUser): RedirectResponse
    {
        if ($adminUser->is(Auth::guard('admin')->user())) {
            return redirect()
                ->route('admin.admin-users.index')
                ->withErrors(['admin_user' => 'You cannot delete your own admin account.']);
        }

        $adminUser->delete();

        return redirect()
            ->route('admin.admin-users.index')
            ->with('status', 'Admin user deleted.');
    }

    private function updateDetails(Request $request, Admin $adminUser): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($adminUser->id)],
            'role' => ['required', Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'admin'))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $adminUser->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $adminUser->save();
        $adminUser->syncRoles([$validated['role']]);

        return redirect()
            ->route('admin.admin-users.edit', $adminUser)
            ->with('status', 'Admin user details updated.')
            ->with('admin_user_tab', 'details');
    }

    private function updatePassword(Request $request, Admin $adminUser): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $adminUser->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return redirect()
            ->route('admin.admin-users.edit', $adminUser)
            ->with('status', 'Admin user password updated.')
            ->with('admin_user_tab', 'password');
    }

    private function updateAvatar(Request $request, Admin $adminUser): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'use_gravatar' => ['nullable', 'boolean'],
        ]);

        $adminUser->forceFill([
            'use_gravatar' => $request->boolean('use_gravatar'),
        ])->save();

        if ($request->boolean('remove_avatar')) {
            $adminUser->removeAvatar();
        } elseif ($request->hasFile('avatar')) {
            $directory = public_path('media/accounts/avatars');
            File::ensureDirectoryExists($directory);

            $file = $request->file('avatar');
            $fileName = 'admin-'.$adminUser->id.'-'.time().'.'.$file->getClientOriginalExtension();
            $file->move($directory, $fileName);

            $adminUser->setAvatarFromFile('accounts/avatars/'.$fileName);
        }

        return redirect()
            ->route('admin.admin-users.edit', $adminUser)
            ->with('status', 'Admin user avatar updated.')
            ->with('admin_user_tab', 'avatar');
    }

    private function roles()
    {
        return Role::query()
            ->where('guard_name', 'admin')
            ->orderByRaw("case when name = 'sys-admin' then 0 when name = 'admin' then 1 else 2 end")
            ->orderBy('name')
            ->get();
    }
}
