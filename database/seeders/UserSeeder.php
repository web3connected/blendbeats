<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name = env('SEEDED_USER_NAME', 'Test User');
        $nameParts = preg_split('/\s+/', trim($name), 2) ?: [];

        User::query()->updateOrCreate(
            ['email' => env('SEEDED_USER_EMAIL', 'test@example.com')],
            [
                'name' => $name,
                'first_name' => $nameParts[0] ?? null,
                'last_name' => $nameParts[1] ?? null,
                'password' => env('SEEDED_USER_PASSWORD', 'password'),
                'email_verified_at' => now(),
                'use_gravatar' => true,
                'is_gravatar' => true,
                'media_storage_tier' => env('SEEDED_USER_MEDIA_STORAGE_TIER', 'starter'),
            ],
        );
    }
}
