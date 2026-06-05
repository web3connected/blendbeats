<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
            ['email' => env('ADMIN_EMAIL', 'richievc@gmail.com')],
            [
                'name' => env('ADMIN_NAME', 'Richard Clark'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
                'role' => 'sys-admin',
                'is_active' => true,
            ],
        );

        if (Role::where('guard_name', 'admin')->where('name', 'sys-admin')->exists()) {
            $admin->syncRoles(['sys-admin']);
        }
    }
}
