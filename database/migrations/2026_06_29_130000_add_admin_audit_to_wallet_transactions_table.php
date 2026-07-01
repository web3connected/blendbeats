<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('admins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_admin_id');
        });
    }
};
