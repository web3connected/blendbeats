<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $primaryAdmin = Admin::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'richievc@gmail.com')],
            [
                'name' => trim(env('ADMIN_FIRST_NAME', 'Richard').' '.env('ADMIN_LAST_NAME', 'Clark')),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'TmasterTM$101')),
                'email_verified_at' => now(),
                'role' => env('ADMIN_ROLE', 'super-admin'),
                'is_active' => true,
                'use_gravatar' => true,
            ],
        );

        $primaryAdmin->syncRoles([$primaryAdmin->role]);

        $secondaryAdmin = Admin::query()->updateOrCreate(
            ['email' => 'williamdelgadojr@outlook.com'],
            [
                'name' => 'William Delgado Jr.',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Secure$101')),
                'email_verified_at' => now(),
                'role' => 'admin',
                'is_active' => true,
                'use_gravatar' => false,
            ],
        );

        $secondaryAdmin->syncRoles([$secondaryAdmin->role]);
    }
}
