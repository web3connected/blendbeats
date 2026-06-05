<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleManagerController extends Controller
{
    public function index(): View
    {
        return view('admin.role-manager.index', [
            'roles' => Role::query()
                ->withCount(['permissions', 'users'])
                ->where('guard_name', 'admin')
                ->orderByRaw("case when name = 'sys-admin' then 0 when name = 'admin' then 1 else 2 end")
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.role-manager.create', [
            'permissions' => $this->permissions(),
            'role' => new Role(['guard_name' => 'admin']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::unique('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'admin'))],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', 'admin'))],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'admin',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.role-manager.index')
            ->with('status', 'Role created.');
    }

    public function edit(Role $roleManager): View
    {
        $this->ensureAdminRole($roleManager);

        return view('admin.role-manager.edit', [
            'permissions' => $this->permissions(),
            'role' => $roleManager->load('permissions'),
        ]);
    }

    public function update(Request $request, Role $roleManager): RedirectResponse
    {
        $this->ensureAdminRole($roleManager);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'admin'))->ignore($roleManager->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', 'admin'))],
        ]);

        if ($roleManager->name === 'sys-admin' && $validated['name'] !== 'sys-admin') {
            return back()->withErrors(['name' => 'The sys-admin role name cannot be changed.']);
        }

        $roleManager->forceFill(['name' => $validated['name']])->save();
        $roleManager->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.role-manager.edit', $roleManager)
            ->with('status', 'Role updated.');
    }

    public function destroy(Role $roleManager): RedirectResponse
    {
        $this->ensureAdminRole($roleManager);

        if ($roleManager->name === 'sys-admin') {
            return redirect()
                ->route('admin.role-manager.index')
                ->withErrors(['role' => 'The sys-admin role cannot be deleted.']);
        }

        if ($roleManager->users()->exists()) {
            return redirect()
                ->route('admin.role-manager.index')
                ->withErrors(['role' => 'Remove this role from admin users before deleting it.']);
        }

        $roleManager->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.role-manager.index')
            ->with('status', 'Role deleted.');
    }

    private function permissions()
    {
        return Permission::query()
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => str($permission->name)->before('.')->toString());
    }

    private function ensureAdminRole(Role $role): void
    {
        abort_unless($role->guard_name === 'admin', 404);
    }
}
