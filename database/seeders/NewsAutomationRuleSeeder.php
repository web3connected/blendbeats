<?php

namespace Database\Seeders;

use App\Models\NewsAutomationRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class NewsAutomationRuleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('news_automation_rules')) {
            return;
        }

        NewsAutomationRule::query()->updateOrCreate(
            ['slug' => 'blendnews-rss-draft-intake'],
            [
                'name' => 'BlendNews RSS Draft Intake',
                'rule_type' => 'rss_intake',
                'event_type' => null,
                'milestone_key' => null,
                'source_type' => 'rss',
                'source_id' => null,
                'condition_field' => null,
                'condition_operator' => null,
                'condition_value' => null,
                'cooldown_minutes' => 30,
                'priority' => 1,
                'is_active' => true,
                'metadata' => [
                    'draft_status' => 'review',
                    'workflow_name' => 'BlendNews RSS Draft Intake',
                ],
            ],
        );
    }
}
