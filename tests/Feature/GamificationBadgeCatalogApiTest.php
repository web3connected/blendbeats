<?php

namespace Tests\Feature;

use App\Models\Badge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationBadgeCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_gamification_badges_endpoint_returns_active_badge_catalog(): void
    {
        Badge::query()->create([
            'badge_key' => 'first_portfolio_upload',
            'name' => 'First Upload',
            'description' => 'Uploaded your first portfolio item.',
            'role_context' => 'dj',
            'icon' => 'badges/first-upload.svg',
            'rarity' => 'common',
            'unlock_action_key' => 'portfolio_uploaded',
            'unlock_threshold' => 1,
            'is_active' => true,
        ]);

        Badge::query()->create([
            'badge_key' => 'retired_badge',
            'name' => 'Retired Badge',
            'description' => 'Inactive badge.',
            'role_context' => 'special',
            'icon' => 'badges/retired.svg',
            'rarity' => 'legendary',
            'unlock_action_key' => 'retired',
            'unlock_threshold' => 1,
            'is_active' => false,
        ]);

        $this->getJson('/api/gamification/badges')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.badge_key', 'first_portfolio_upload')
            ->assertJsonPath('0.name', 'First Upload')
            ->assertJsonPath('0.description', 'Uploaded your first portfolio item.')
            ->assertJsonPath('0.role_context', 'dj')
            ->assertJsonPath('0.icon', 'badges/first-upload.svg')
            ->assertJsonPath('0.rarity', 'common')
            ->assertJsonPath('0.unlock_action_key', 'portfolio_uploaded')
            ->assertJsonPath('0.unlock_threshold', 1)
            ->assertJsonPath('0.unlock_condition', 'Upload first portfolio item')
            ->assertJsonMissing(['badge_key' => 'retired_badge']);
    }
}
