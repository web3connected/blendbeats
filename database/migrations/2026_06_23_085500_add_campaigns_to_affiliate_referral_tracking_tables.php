<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_referral_codes', function (Blueprint $table): void {
            $table->foreignId('affiliate_campaign_id')
                ->nullable()
                ->after('affiliate_account_id')
                ->constrained('affiliate_campaigns')
                ->nullOnDelete();
        });

        Schema::table('affiliate_referral_visits', function (Blueprint $table): void {
            $table->foreignId('affiliate_campaign_id')
                ->nullable()
                ->after('affiliate_account_id')
                ->constrained('affiliate_campaigns')
                ->nullOnDelete();
        });

        Schema::table('affiliate_referrals', function (Blueprint $table): void {
            $table->foreignId('affiliate_campaign_id')
                ->nullable()
                ->after('affiliate_account_id')
                ->constrained('affiliate_campaigns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_referrals', function (Blueprint $table): void {
            $table->dropForeign(['affiliate_campaign_id']);
            $table->dropColumn('affiliate_campaign_id');
        });

        Schema::table('affiliate_referral_visits', function (Blueprint $table): void {
            $table->dropForeign(['affiliate_campaign_id']);
            $table->dropColumn('affiliate_campaign_id');
        });

        Schema::table('affiliate_referral_codes', function (Blueprint $table): void {
            $table->dropForeign(['affiliate_campaign_id']);
            $table->dropColumn('affiliate_campaign_id');
        });
    }
};
