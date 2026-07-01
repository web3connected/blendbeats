<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battle_escrows', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('battle_id')->unique()->constrained('dj_battles')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('escrow_mode')->default('demo');
            $table->string('currency_type', 24)->default('TOKENS');
            $table->unsignedBigInteger('stake_amount')->default(0);

            $table->foreignId('challenger_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opponent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('challenger_lock_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('opponent_lock_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();

            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_reward_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('platform_fee_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->unsignedBigInteger('fan_reward_pool_amount')->default(0);
            $table->unsignedBigInteger('prize_pool_amount')->default(0);

            $table->boolean('requires_admin_review')->default(false);
            $table->unsignedInteger('settlement_attempts')->default(0);
            $table->text('last_settlement_error')->nullable();

            $table->timestamp('locked_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['escrow_mode', 'status']);
            $table->index(['requires_admin_review', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_escrows');
    }
};
