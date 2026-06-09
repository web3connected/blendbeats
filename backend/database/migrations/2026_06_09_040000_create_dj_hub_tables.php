<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dj_featured_status', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dj_profile_id')->constrained('dj_profiles')->cascadeOnDelete();
            $table->string('featured_type');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['featured_type', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('followers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followed_dj_id')->constrained('dj_profiles')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['follower_user_id', 'followed_dj_id']);
            $table->index(['followed_dj_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followers');
        Schema::dropIfExists('dj_featured_status');
    }
};
