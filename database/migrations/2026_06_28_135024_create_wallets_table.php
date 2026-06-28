<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // Token Balances
            $table->unsignedBigInteger('available_balance')->default(0);
            $table->unsignedBigInteger('locked_balance')->default(0);

            // Lifetime Statistics
            $table->unsignedBigInteger('lifetime_earned')->default(0);
            $table->unsignedBigInteger('lifetime_spent')->default(0);
            $table->unsignedBigInteger('lifetime_withdrawn')->default(0);

            // Wallet Status
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
