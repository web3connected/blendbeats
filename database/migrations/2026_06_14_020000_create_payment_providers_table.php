<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->unique();
            $table->string('display_name');
            $table->string('mode')->default('test');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->string('client_id')->nullable();
            $table->text('secret')->nullable();
            $table->string('webhook_id')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('dashboard_url')->nullable();
            $table->text('notes')->nullable();
            $table->json('supported_features')->nullable();
            $table->timestamps();
        });

        DB::table('payment_providers')->insert([
            [
                'provider' => 'paypal',
                'display_name' => 'PayPal',
                'mode' => 'sandbox',
                'is_active' => true,
                'is_primary' => true,
                'dashboard_url' => 'https://www.paypal.com/businessmanage/account',
                'supported_features' => json_encode(['checkout', 'subscriptions', 'promotion_payments']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'provider' => 'stripe',
                'display_name' => 'Stripe',
                'mode' => 'test',
                'is_active' => false,
                'is_primary' => false,
                'dashboard_url' => 'https://dashboard.stripe.com/test/dashboard',
                'supported_features' => json_encode(['checkout', 'subscriptions', 'billing_portal', 'webhooks']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_providers');
    }
};
