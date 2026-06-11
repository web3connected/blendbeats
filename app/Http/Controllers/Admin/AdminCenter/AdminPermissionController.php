<?php

namespace App\Http\Controllers\Admin\AdminCenter;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminPermissionController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::query()
            ->with('roles')
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->get();

        return view('admin.admin-permissions.index', [
            'permissionsByModule' => $permissions->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString()),
            'permissionCount' => $permissions->count(),
            'roleCount' => Role::query()->where('guard_name', 'admin')->count(),
            'roles' => Role::query()
                ->withCount('permissions')
                ->where('guard_name', 'admin')
                ->orderByRaw("case when name = 'super-admin' then 0 when name = 'admin' then 1 when name = 'manager' then 2 else 3 end")
                ->orderBy('name')
                ->get(),
        ]);
    }
}
