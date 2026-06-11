<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'adminusers.view',
            'adminusers.create',
            'adminusers.update',
            'adminusers.delete',
            'adminusers.reset-password',
            'adminusers.manage-avatar',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $roles = [
            'super-admin' => [
                'display_name' => 'Super Admin',
                'description' => 'Full access to Admin User management.',
                'is_system' => true,
                'permissions' => $permissions,
            ],
            'admin' => [
                'display_name' => 'Administrator',
                'description' => 'Can view, create, update, reset passwords, and manage avatars for admin users.',
                'is_system' => true,
                'permissions' => [
                    'adminusers.view',
                    'adminusers.create',
                    'adminusers.update',
                    'adminusers.reset-password',
                    'adminusers.manage-avatar',
                ],
            ],
            'manager' => [
                'display_name' => 'Manager',
                'description' => 'Can view admin users only.',
                'is_system' => false,
                'permissions' => [
                    'adminusers.view',
                ],
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            $role = Role::findOrCreate($roleName, 'admin');

            $role->forceFill([
                'display_name' => $roleData['display_name'],
                'description' => $roleData['description'],
                'is_system' => $roleData['is_system'],
            ])->save();

            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'admin')
                    ->whereIn('name', $roleData['permissions'])
                    ->get(),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
