<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('status')->default('requested');
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('reward_count')->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payout_reference')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['affiliate_account_id', 'status']);
            $table->index('requested_at');
        });

        Schema::table('affiliate_rewards', function (Blueprint $table): void {
            $table->foreignId('affiliate_payout_id')
                ->nullable()
                ->after('affiliate_referral_id')
                ->constrained('affiliate_payouts')
                ->nullOnDelete();

            $table->index('affiliate_payout_id');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_rewards', function (Blueprint $table): void {
            $table->dropIndex(['affiliate_payout_id']);
            $table->dropForeign(['affiliate_payout_id']);
            $table->dropColumn('affiliate_payout_id');
        });

        Schema::dropIfExists('affiliate_payouts');
    }
};
