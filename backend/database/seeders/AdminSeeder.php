<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(AdminRoleSeeder::class);

        $admin = Admin::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@blendbeats.local')],
            [
                'name' => env('ADMIN_NAME', 'BlendBeats Admin'),
                'password' => env('ADMIN_PASSWORD', 'password'),
                'role' => 'sys-admin',
                'is_active' => true,
            ],
        );

        if (Role::where('guard_name', 'admin')->where('name', 'sys-admin')->exists()) {
            $admin->syncRoles(['sys-admin']);
        }
    }
}
