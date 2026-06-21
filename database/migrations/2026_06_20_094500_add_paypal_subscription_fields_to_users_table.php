<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'paypal_subscription_id')) {
                $table->string('paypal_subscription_id')->nullable()->after('trial_ends_at');
            }

            if (! Schema::hasColumn('users', 'paypal_plan_id')) {
                $table->string('paypal_plan_id')->nullable()->after('paypal_subscription_id');
            }

            if (! Schema::hasColumn('users', 'paypal_subscription_status')) {
                $table->string('paypal_subscription_status')->nullable()->after('paypal_plan_id');
            }

            if (! Schema::hasColumn('users', 'paypal_subscription_approved_at')) {
                $table->timestamp('paypal_subscription_approved_at')->nullable()->after('paypal_subscription_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'paypal_subscription_approved_at',
                'paypal_subscription_status',
                'paypal_plan_id',
                'paypal_subscription_id',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
