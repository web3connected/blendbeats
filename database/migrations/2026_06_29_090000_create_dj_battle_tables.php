<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dj_battles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('challenger_dj_profile_id')->constrained('dj_profiles')->cascadeOnDelete();
            $table->foreignId('opponent_dj_profile_id')->constrained('dj_profiles')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('battle_type')->default('open_format');
            $table->string('status')->default('pending');
            $table->string('title');
            $table->string('theme')->nullable();
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->default(180);
            $table->unsignedSmallInteger('minimum_votes')->default(1);
            $table->unsignedBigInteger('stake_amount')->default(0);
            $table->string('currency', 12)->default('TOKENS');
            $table->foreignId('winner_dj_profile_id')->nullable()->constrained('dj_profiles')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('recording_started_at')->nullable();
            $table->timestamp('voting_started_at')->nullable();
            $table->timestamp('voting_ends_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['battle_type', 'status']);
            $table->index(['challenger_dj_profile_id', 'status']);
            $table->index(['opponent_dj_profile_id', 'status']);
        });

        Schema::create('dj_battle_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('battle_id')->constrained('dj_battles')->cascadeOnDelete();
            $table->foreignId('dj_profile_id')->constrained('dj_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->foreignId('audio_media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recording_started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['battle_id', 'dj_profile_id']);
            $table->index(['battle_id', 'status']);
            $table->index(['dj_profile_id', 'created_at']);
        });

        Schema::create('dj_battle_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('battle_id')->constrained('dj_battles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prediction_dj_profile_id')->nullable()->constrained('dj_profiles')->nullOnDelete();
            $table->unsignedSmallInteger('vote_weight')->default(1);
            $table->boolean('reward_eligible')->default(false);
            $table->timestamp('watched_challenger_at')->nullable();
            $table->timestamp('watched_opponent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['battle_id', 'user_id']);
            $table->index(['battle_id', 'submitted_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('dj_battle_vote_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vote_id')->constrained('dj_battle_votes')->cascadeOnDelete();
            $table->foreignId('battle_id')->constrained('dj_battles')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('dj_battle_entries')->cascadeOnDelete();
            $table->foreignId('dj_profile_id')->constrained('dj_profiles')->cascadeOnDelete();
            $table->unsignedTinyInteger('mixing_score');
            $table->unsignedTinyInteger('scratching_score');
            $table->unsignedTinyInteger('creativity_score');
            $table->unsignedTinyInteger('track_selection_score');
            $table->decimal('total_score', 5, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['vote_id', 'entry_id']);
            $table->index(['battle_id', 'dj_profile_id']);
        });

        Schema::create('dj_battle_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('battle_id')->unique()->constrained('dj_battles')->cascadeOnDelete();
            $table->foreignId('winner_dj_profile_id')->nullable()->constrained('dj_profiles')->nullOnDelete();
            $table->decimal('challenger_score', 6, 3)->default(0);
            $table->decimal('opponent_score', 6, 3)->default(0);
            $table->unsignedInteger('total_votes')->default(0);
            $table->unsignedInteger('total_vote_weight')->default(0);
            $table->boolean('is_draw')->default(false);
            $table->string('calculation_version')->default('mvp-v1');
            $table->json('score_snapshot')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['winner_dj_profile_id', 'calculated_at']);
        });

        Schema::create('dj_battle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('battle_id')->constrained('dj_battles')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['battle_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dj_battle_events');
        Schema::dropIfExists('dj_battle_results');
        Schema::dropIfExists('dj_battle_vote_scores');
        Schema::dropIfExists('dj_battle_votes');
        Schema::dropIfExists('dj_battle_entries');
        Schema::dropIfExists('dj_battles');
    }
};
