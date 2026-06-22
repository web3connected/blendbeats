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
        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->string('badge_key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('role_context')->default('both');
            $table->string('icon')->nullable();
            $table->string('rarity')->default('common');
            $table->string('unlock_action_key')->nullable();
            $table->unsignedInteger('unlock_threshold')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('role_context');
            $table->index('unlock_action_key');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
