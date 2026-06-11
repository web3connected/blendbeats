<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use SebastianBergmann\Type\FalseType;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'richievc@gmail.com')],
            [
                'first_name' => env('ADMIN_FIRST_NAME', 'Richard'),
                'last_name' => env('ADMIN_LAST_NAME', 'Clark'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'TmasterTM$101')),
                'email_verified_at' => now(),
                'role' => env('ADMIN_ROLE', 'sys-admin'),
                'is_active' => true,
                'use_gravatar' => true,
            ],
        );

         Admin::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'williamdelgadojr@outlook.com')],
            [
                'first_name' => env('ADMIN_FIRST_NAME', 'William'),
                'last_name' => env('ADMIN_LAST_NAME', 'Delgado Jr.'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Secure$101')),
                'email_verified_at' => now(),
                'role' => env('ADMIN_ROLE', 'admin'),
                'is_active' => true,
                'use_gravatar' => FalseType,
            ],
        );
    }
}
