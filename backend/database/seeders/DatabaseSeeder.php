<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);

        User::updateOrCreate(
            ['email' => env('SEEDED_USER_EMAIL', 'test@example.com')],
            [
                'name' => env('SEEDED_USER_NAME', 'Test User'),
                'password' => env('SEEDED_USER_PASSWORD', 'password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
