<?php

namespace Database\Seeders;

use App\Models\GamificationAction;
use Illuminate\Database\Seeder;

class GamificationActionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = [
            [
                'action_key' => 'portfolio_uploaded',
                'label' => 'Portfolio Uploaded',
                'role_context' => 'dj',
                'xp_amount' => 25,
                'once_per_target' => false,
                'is_active' => true,
            ],
            [
                'action_key' => 'scratch_uploaded',
                'label' => 'Scratch Uploaded',
                'role_context' => 'dj',
                'xp_amount' => 40,
                'once_per_target' => false,
                'is_active' => true,
            ],
            [
                'action_key' => 'dj_followed',
                'label' => 'DJ Followed',
                'role_context' => 'fan',
                'xp_amount' => 15,
                'once_per_target' => true,
                'is_active' => true,
            ],
            [
                'action_key' => 'mix_saved_to_playlist',
                'label' => 'Mix Saved To Playlist',
                'role_context' => 'fan',
                'xp_amount' => 10,
                'once_per_target' => true,
                'is_active' => true,
            ],
            [
                'action_key' => 'mix_liked',
                'label' => 'Mix Liked',
                'role_context' => 'fan',
                'xp_amount' => 5,
                'once_per_target' => true,
                'is_active' => true,
            ],
            [
                'action_key' => 'battle_voted',
                'label' => 'Battle Voted',
                'role_context' => 'fan',
                'xp_amount' => 10,
                'once_per_target' => true,
                'is_active' => true,
            ],
            [
                'action_key' => 'daily_login',
                'label' => 'Daily Login',
                'role_context' => 'fan',
                'xp_amount' => 10,
                'daily_limit' => 1,
                'once_per_target' => false,
                'is_active' => true,
            ],
        ];

        foreach ($actions as $action) {
            GamificationAction::query()->updateOrCreate(
                ['action_key' => $action['action_key']],
                $action,
            );
        }
    }
}
