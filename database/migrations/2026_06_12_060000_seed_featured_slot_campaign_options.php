<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('featured_slot_campaign_options')) {
            return;
        }

        $now = now();

        collect([
            [
                'name' => '1 Day Spotlight',
                'description' => 'Runs for 24 hours after approval.',
                'duration_days' => 1,
                'price_cents' => null,
                'sort_order' => 10,
            ],
            [
                'name' => '7 Day Spotlight',
                'description' => 'Runs for one full week after approval.',
                'duration_days' => 7,
                'price_cents' => null,
                'sort_order' => 20,
            ],
        ])->each(function (array $option) use ($now): void {
            DB::table('featured_slot_campaign_options')->updateOrInsert(
                ['name' => $option['name']],
                [
                    ...$option,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('featured_slot_campaign_options')) {
            return;
        }

        DB::table('featured_slot_campaign_options')
            ->whereIn('name', ['1 Day Spotlight', '7 Day Spotlight'])
            ->delete();
    }
};
