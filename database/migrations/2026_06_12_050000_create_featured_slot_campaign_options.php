<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_slot_campaign_options', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedInteger('price_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->foreignId('featured_slot_campaign_option_id')
                ->nullable()
                ->after('slot_number')
                ->constrained('featured_slot_campaign_options')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_slot_campaign_option_id');
        });

        Schema::dropIfExists('featured_slot_campaign_options');
    }
};
