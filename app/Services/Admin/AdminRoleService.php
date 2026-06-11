<?php

namespace App\Services\Admin;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleService
{
    public function permissionsByModule(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString());
    }

    public function assignPermissions(Role $role, array $permissions): void
    {
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function orderedRoles()
    {
        return Role::query()
            ->where('guard_name', 'admin')
            ->orderByRaw("case when name = 'super-admin' then 0 when name = 'admin' then 1 when name = 'manager' then 2 else 3 end")
            ->orderBy('name')
            ->get();
    }

    public function syncAdminRole($admin, Role $role): void
    {
        $admin->forceFill(['role' => $role->name])->save();
        $admin->syncRoles([$role]);
    }
}
