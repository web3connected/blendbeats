<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->timestamp('pending_payment_notified_at')->nullable()->after('end_date');
            $table->timestamp('activated_notified_at')->nullable()->after('pending_payment_notified_at');
            $table->timestamp('ending_soon_notified_at')->nullable()->after('activated_notified_at');
            $table->timestamp('expired_notified_at')->nullable()->after('ending_soon_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->dropColumn([
                'pending_payment_notified_at',
                'activated_notified_at',
                'ending_soon_notified_at',
                'expired_notified_at',
            ]);
        });
    }
};
