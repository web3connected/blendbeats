<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@blendbeats.local')],
            [
                'name' => env('ADMIN_NAME', 'BlendBeats Admin'),
                'password' => env('ADMIN_PASSWORD', 'password'),
                'role' => 'super_admin',
                'is_active' => true,
            ],
        );
    }
}
