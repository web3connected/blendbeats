<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->unsignedInteger('amount_cents')->default(0)->after('rotation_weight');
            $table->string('currency', 3)->default('USD')->after('amount_cents');
            $table->string('payment_provider')->nullable()->after('currency');
            $table->string('payment_status')->default('pending')->after('payment_provider');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->timestamp('claimed_at')->nullable()->after('payment_reference');
            $table->json('payment_metadata')->nullable()->after('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->dropColumn([
                'amount_cents',
                'currency',
                'payment_provider',
                'payment_status',
                'payment_reference',
                'claimed_at',
                'payment_metadata',
            ]);
        });
    }
};
