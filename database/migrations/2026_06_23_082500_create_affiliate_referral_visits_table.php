<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_referral_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_referral_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_account_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_id')->nullable();
            $table->text('landing_url')->nullable();
            $table->text('referrer_url')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('affiliate_referral_code_id');
            $table->index('affiliate_account_id');
            $table->index('visitor_id');
            $table->index('visited_at');
            $table->index('converted_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referral_visits');
    }
};
