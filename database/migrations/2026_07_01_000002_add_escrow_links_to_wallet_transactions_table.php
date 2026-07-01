<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->foreignId('battle_escrow_id')
                ->nullable()
                ->after('related_id')
                ->constrained('battle_escrows')
                ->nullOnDelete();
            $table->foreignId('reverses_transaction_id')
                ->nullable()
                ->after('battle_escrow_id')
                ->constrained('wallet_transactions')
                ->nullOnDelete();
            $table->uuid('settlement_group_uuid')->nullable()->after('reverses_transaction_id');
            $table->string('idempotency_key')->nullable()->after('settlement_group_uuid');

            $table->index(['battle_escrow_id', 'created_at']);
            $table->index('settlement_group_uuid');
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->dropIndex(['battle_escrow_id', 'created_at']);
            $table->dropIndex(['settlement_group_uuid']);
            $table->dropIndex(['idempotency_key']);
            $table->dropConstrainedForeignId('battle_escrow_id');
            $table->dropConstrainedForeignId('reverses_transaction_id');
            $table->dropColumn(['settlement_group_uuid', 'idempotency_key']);
        });
    }
};
