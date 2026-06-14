<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ad_credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credit_type')->default('featured_ad_day');
            $table->string('source')->default('registration_bonus');
            $table->string('code')->unique();
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('remaining_quantity')->default(1);
            $table->string('discount_type')->default('percent');
            $table->unsignedInteger('discount_value')->default(100);
            $table->string('status')->default('active');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'credit_type', 'source'], 'user_ad_credit_once_per_source');
            $table->index(['user_id', 'status', 'credit_type']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ad_credits');
    }
};
