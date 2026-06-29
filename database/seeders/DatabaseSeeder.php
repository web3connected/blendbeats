<?php

namespace Database\Seeders;

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
        $this->call([
            AdminRoleSeeder::class,
            AdminSeeder::class,
            UserSeeder::class,
            RegistrationAdCreditSeeder::class,
            CommerceProductSeeder::class,
            NewsAutomationRuleSeeder::class,
        ]);

        if (filter_var(env('SEED_BATTLE_DEMO_USERS', false), FILTER_VALIDATE_BOOL)) {
            $this->call(BattleDemoSeeder::class);
        }
    }
}
