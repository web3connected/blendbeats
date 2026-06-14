<?php

use App\Services\FeaturedPlacementPricingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('featured_slot_groups')) {
            return;
        }

        $pricing = app(FeaturedPlacementPricingService::class);
        $now = now();

        foreach (range(1, FeaturedPlacementPricingService::GROUP_COUNT) as $groupNumber) {
            DB::table('featured_slot_groups')
                ->where('sort_order', $groupNumber)
                ->update([
                    'daily_price_cents' => $pricing->dailyPriceCents($groupNumber),
                    'rotation_weight' => $pricing->rotationWeight($groupNumber),
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        //
    }
};
