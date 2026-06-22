<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_gamification_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('dj_xp')->default(0);
            $table->unsignedBigInteger('fan_xp')->default(0);
            $table->unsignedBigInteger('total_xp')->default(0);
            $table->unsignedInteger('dj_level')->default(1);
            $table->unsignedInteger('fan_level')->default(1);
            $table->unsignedInteger('total_level')->default(1);
            $table->string('dj_rank')->nullable();
            $table->string('fan_rank')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('dj_level');
            $table->index('fan_level');
            $table->index('total_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_gamification_stats');
    }
};
