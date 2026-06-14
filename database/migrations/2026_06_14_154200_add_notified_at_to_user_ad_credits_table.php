<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_ad_credits', function (Blueprint $table): void {
            $table->timestamp('notified_at')->nullable()->after('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_ad_credits', function (Blueprint $table): void {
            $table->dropColumn('notified_at');
        });
    }
};
