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
        Schema::create('gamification_actions', function (Blueprint $table): void {
            $table->id();
            $table->string('action_key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('role_context')->default('both');
            $table->integer('xp_amount')->default(0);
            $table->integer('daily_limit')->nullable();
            $table->integer('weekly_limit')->nullable();
            $table->boolean('once_per_target')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('role_context');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gamification_actions');
    }
};
