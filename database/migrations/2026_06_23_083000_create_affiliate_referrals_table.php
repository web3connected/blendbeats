<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_referral_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('affiliate_referral_visit_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('attribution_type')->default('signup');
            $table->timestamp('attributed_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->string('qualified_transaction_type')->nullable();
            $table->string('qualified_transaction_id')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('affiliate_account_id');
            $table->index('affiliate_referral_code_id');
            $table->index('status');
            $table->index('attributed_at');
            $table->index(['qualified_transaction_type', 'qualified_transaction_id'], 'affiliate_referrals_qualified_transaction_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referrals');
    }
};
