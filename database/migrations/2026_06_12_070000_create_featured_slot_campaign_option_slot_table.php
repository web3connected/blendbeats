<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_slot_campaign_option_slot', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('slot_number');
            $table->foreignId('featured_slot_campaign_option_id')
                ->constrained('featured_slot_campaign_options')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['slot_number', 'featured_slot_campaign_option_id'], 'featured_slot_option_unique');
            $table->index('slot_number');
        });

        if (Schema::hasTable('featured_slot_campaign_options')) {
            $now = now();
            $optionIds = DB::table('featured_slot_campaign_options')
                ->where('is_active', true)
                ->pluck('id');

            foreach (range(1, 24) as $slotNumber) {
                foreach ($optionIds as $optionId) {
                    DB::table('featured_slot_campaign_option_slot')->updateOrInsert(
                        [
                            'slot_number' => $slotNumber,
                            'featured_slot_campaign_option_id' => $optionId,
                        ],
                        [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_slot_campaign_option_slot');
    }
};
