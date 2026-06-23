<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_referral_id')->constrained()->cascadeOnDelete();
            $table->string('reward_type')->default('future_incentive');
            $table->string('source')->default('subscription_qualification');
            $table->string('status')->default('pending');
            $table->unsignedInteger('amount_cents')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('points')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('issued_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['affiliate_referral_id', 'reward_type', 'source'], 'affiliate_reward_once_per_referral_source');
            $table->index('affiliate_account_id');
            $table->index('affiliate_referral_id');
            $table->index('reward_type');
            $table->index('status');
            $table->index(['status', 'available_at']);
            $table->index(['issued_at', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_rewards');
    }
};
