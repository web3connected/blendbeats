<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dj_battles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('voting_duration_hours')->default(24);
            $table->text('challenge_message')->nullable();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('ready_due_at')->nullable();
            $table->timestamp('challenger_ready_at')->nullable();
            $table->timestamp('opponent_ready_at')->nullable();
            $table->timestamp('recording_ends_at')->nullable();
            $table->unsignedBigInteger('fan_reward_pool_amount')->default(0);
            $table->unsignedBigInteger('prize_pool_amount')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('dj_battles', function (Blueprint $table): void {
            $table->dropColumn([
                'voting_duration_hours',
                'challenge_message',
                'response_due_at',
                'ready_due_at',
                'challenger_ready_at',
                'opponent_ready_at',
                'recording_ends_at',
                'fan_reward_pool_amount',
                'prize_pool_amount',
            ]);
        });
    }
};
