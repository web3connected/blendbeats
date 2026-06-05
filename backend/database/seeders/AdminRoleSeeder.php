<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'account.manage',
            'admin-users.manage',
            'roles.manage',
            'user-accounts.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $roles = [
            'sys-admin' => $permissions,
            'admin' => [
                'dashboard.view',
                'account.manage',
                'admin-users.manage',
                'user-accounts.manage',
            ],
            'content-manager' => [
                'dashboard.view',
                'account.manage',
                'user-accounts.manage',
            ],
            'support' => [
                'dashboard.view',
                'account.manage',
                'user-accounts.manage',
            ],
            'viewer' => [
                'dashboard.view',
                'account.manage',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName, 'admin')
                ->syncPermissions(
                    Permission::query()
                        ->where('guard_name', 'admin')
                        ->whereIn('name', $rolePermissions)
                        ->get(),
                );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
