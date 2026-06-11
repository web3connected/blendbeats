<?php

namespace App\Http\Controllers\Admin\AdminCenter;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\AdminRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleController extends Controller
{
    public function __construct(private readonly AdminRoleService $roleService) {}

    public function index(): View
    {
        return view('admin.admin-roles.index', [
            'roles' => Role::query()
                ->withCount(['permissions', 'users'])
                ->where('guard_name', 'admin')
                ->orderByRaw("case when name = 'super-admin' then 0 when name = 'admin' then 1 when name = 'manager' then 2 else 3 end")
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.admin-roles.create', [
            'permissions' => $this->roleService->permissionsByModule(),
            'role' => new Role(['guard_name' => 'admin']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRole($request);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_system' => $request->boolean('is_system'),
            'guard_name' => 'admin',
        ]);

        $this->roleService->assignPermissions($role, $validated['permissions'] ?? []);

        return redirect()
            ->route('admin.admincenter.adminroles.show', $role)
            ->with('status', 'Admin role created.');
    }

    public function show(Role $adminrole): View
    {
        $this->ensureAdminRole($adminrole);

        return view('admin.admin-roles.show', [
            'permissions' => $this->roleService->permissionsByModule(),
            'role' => $adminrole->load(['permissions', 'users']),
        ]);
    }

    public function edit(Role $adminrole): View
    {
        $this->ensureAdminRole($adminrole);

        return view('admin.admin-roles.edit', [
            'permissions' => $this->roleService->permissionsByModule(),
            'role' => $adminrole->load('permissions'),
        ]);
    }

    public function update(Request $request, Role $adminrole): RedirectResponse
    {
        $this->ensureAdminRole($adminrole);

        $validated = $this->validateRole($request, $adminrole);

        if ($adminrole->name === 'super-admin' && $validated['name'] !== 'super-admin') {
            return back()->withErrors(['name' => 'The super-admin role name cannot be changed.']);
        }

        $adminrole->forceFill([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_system' => $request->boolean('is_system'),
        ])->save();

        $this->roleService->assignPermissions($adminrole, $validated['permissions'] ?? []);

        Admin::query()
            ->whereKey($adminrole->users()->pluck('admins.id'))
            ->update(['role' => $adminrole->name]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.admincenter.adminroles.edit', $adminrole)
            ->with('status', 'Admin role updated.');
    }

    public function destroy(Role $adminrole): RedirectResponse
    {
        $this->ensureAdminRole($adminrole);

        if ($adminrole->is_system || $adminrole->name === 'super-admin') {
            return redirect()
                ->route('admin.admincenter.adminroles.index')
                ->withErrors(['role' => 'System roles cannot be deleted.']);
        }

        if ($adminrole->users()->exists()) {
            return redirect()
                ->route('admin.admincenter.adminroles.index')
                ->withErrors(['role' => 'Remove this role from admin users before deleting it.']);
        }

        $adminrole->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.admincenter.adminroles.index')
            ->with('status', 'Admin role deleted.');
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'admin'))
                    ->ignore($role?->id),
            ],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_system' => ['nullable', 'boolean'],
            'permissions' => ['array'],
            'permissions.*' => [
                Rule::exists('permissions', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'admin')),
            ],
        ]);
    }

    private function ensureAdminRole(Role $role): void
    {
        abort_unless($role->guard_name === 'admin', 404);
    }
}
