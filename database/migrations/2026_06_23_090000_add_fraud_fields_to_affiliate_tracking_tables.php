<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_referral_visits', function (Blueprint $table): void {
            $table->boolean('is_suspicious')->default(false)->after('converted_at');
            $table->string('suspicious_reason')->nullable()->after('is_suspicious');
            $table->timestamp('suspicious_at')->nullable()->after('suspicious_reason');

            $table->index('is_suspicious');
            $table->index('suspicious_reason');
        });

        Schema::table('affiliate_referrals', function (Blueprint $table): void {
            $table->boolean('is_suspicious')->default(false)->after('rejection_reason');
            $table->string('fraud_reason')->nullable()->after('is_suspicious');
            $table->json('fraud_flags')->nullable()->after('fraud_reason');
            $table->timestamp('fraud_checked_at')->nullable()->after('fraud_flags');

            $table->index('is_suspicious');
            $table->index('fraud_reason');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_referrals', function (Blueprint $table): void {
            $table->dropIndex(['is_suspicious']);
            $table->dropIndex(['fraud_reason']);
            $table->dropColumn([
                'is_suspicious',
                'fraud_reason',
                'fraud_flags',
                'fraud_checked_at',
            ]);
        });

        Schema::table('affiliate_referral_visits', function (Blueprint $table): void {
            $table->dropIndex(['is_suspicious']);
            $table->dropIndex(['suspicious_reason']);
            $table->dropColumn([
                'is_suspicious',
                'suspicious_reason',
                'suspicious_at',
            ]);
        });
    }
};
