<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $badges = [
            [
                'badge_key' => 'first_portfolio_upload',
                'name' => 'First Upload',
                'description' => 'Uploaded your first portfolio item.',
                'role_context' => 'dj',
                'icon' => 'badges/first-upload.svg',
                'rarity' => 'common',
                'unlock_action_key' => 'portfolio_uploaded',
                'unlock_threshold' => 1,
                'is_active' => true,
            ],
            [
                'badge_key' => 'first_scratch_upload',
                'name' => 'First Scratch',
                'description' => 'Uploaded your first scratch routine.',
                'role_context' => 'dj',
                'icon' => 'badges/first-scratch.svg',
                'rarity' => 'common',
                'unlock_action_key' => 'scratch_uploaded',
                'unlock_threshold' => 1,
                'is_active' => true,
            ],
            [
                'badge_key' => 'battle_voter',
                'name' => 'Battle Voter',
                'description' => 'Cast your first battle vote.',
                'role_context' => 'fan',
                'icon' => 'badges/battle-voter.svg',
                'rarity' => 'common',
                'unlock_action_key' => 'battle_voted',
                'unlock_threshold' => 1,
                'is_active' => true,
            ],
            [
                'badge_key' => 'fan_favorite',
                'name' => 'Fan Favorite',
                'description' => 'Reached a DJ engagement milestone.',
                'role_context' => 'dj',
                'icon' => 'badges/fan-favorite.svg',
                'rarity' => 'rare',
                'unlock_action_key' => 'mix_liked',
                'unlock_threshold' => 25,
                'is_active' => true,
            ],
            [
                'badge_key' => 'weekly_grinder',
                'name' => 'Weekly Grinder',
                'description' => 'Completed a weekly activity streak.',
                'role_context' => 'both',
                'icon' => 'badges/weekly-grinder.svg',
                'rarity' => 'rare',
                'unlock_action_key' => 'daily_login',
                'unlock_threshold' => 7,
                'is_active' => true,
            ],
            [
                'badge_key' => 'super_fan',
                'name' => 'Super Fan',
                'description' => 'Reached a fan engagement milestone.',
                'role_context' => 'fan',
                'icon' => 'badges/super-fan.svg',
                'rarity' => 'rare',
                'unlock_action_key' => 'dj_followed',
                'unlock_threshold' => 10,
                'is_active' => true,
            ],
        ];

        foreach ($badges as $badge) {
            Badge::query()->updateOrCreate(
                ['badge_key' => $badge['badge_key']],
                $badge,
            );
        }
    }
}
