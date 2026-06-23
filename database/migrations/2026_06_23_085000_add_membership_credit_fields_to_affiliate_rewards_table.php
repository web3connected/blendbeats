<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_rewards', function (Blueprint $table): void {
            $table->unsignedInteger('membership_credit_days')
                ->nullable()
                ->after('quantity');
            $table->timestamp('expires_at')
                ->nullable()
                ->after('available_at');
            $table->timestamp('redeemed_at')
                ->nullable()
                ->after('paid_at');

            $table->index(['reward_type', 'status', 'expires_at'], 'affiliate_rewards_credit_expiry_index');
            $table->index('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_rewards', function (Blueprint $table): void {
            $table->dropIndex('affiliate_rewards_credit_expiry_index');
            $table->dropIndex(['redeemed_at']);
            $table->dropColumn([
                'redeemed_at',
                'expires_at',
                'membership_credit_days',
            ]);
        });
    }
};
