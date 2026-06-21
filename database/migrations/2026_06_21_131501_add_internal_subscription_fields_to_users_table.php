<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_provider')
                ->nullable()
                ->after('paypal_subscription_status');

            $table->timestamp('comped_subscription_expires_at')
                ->nullable()
                ->after('billing_provider');

            $table->string('comped_subscription_reason')
                ->nullable()
                ->after('comped_subscription_expires_at');

            $table->foreignId('comped_by_user_id')
                ->nullable()
                ->after('comped_subscription_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'comped_by_user_id',
                'comped_subscription_reason',
                'comped_subscription_expires_at',
                'billing_provider',
            ]);
        });
    }
};
