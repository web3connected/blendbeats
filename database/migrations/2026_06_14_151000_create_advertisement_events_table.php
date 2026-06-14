<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisement_events', function (Blueprint $table): void {
            $table->id();
            $table->string('advertisable_type');
            $table->unsignedBigInteger('advertisable_id');
            $table->string('event_type', 32);
            $table->string('placement', 100)->nullable();
            $table->string('session_id')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['advertisable_type', 'advertisable_id', 'event_type', 'created_at'], 'ad_events_ad_event_created_index');
            $table->index(['placement', 'event_type', 'created_at'], 'ad_events_placement_event_created_index');
        });

        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->unsignedBigInteger('impression_count')->default(0)->after('payment_metadata');
            $table->unsignedBigInteger('click_count')->default(0)->after('impression_count');
        });
    }

    public function down(): void
    {
        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->dropColumn(['impression_count', 'click_count']);
        });

        Schema::dropIfExists('advertisement_events');
    }
};
