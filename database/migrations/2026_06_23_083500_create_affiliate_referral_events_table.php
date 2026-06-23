<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_referral_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_referral_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('event_source')->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('event_hash')->unique();
            $table->timestamp('occurred_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('affiliate_referral_id');
            $table->index('event_type');
            $table->index(['transaction_type', 'transaction_id'], 'affiliate_referral_events_transaction_index');
            $table->index(['target_type', 'target_id']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referral_events');
    }
};
