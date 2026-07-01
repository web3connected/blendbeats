<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dj_battle_vote_scores', function (Blueprint $table): void {
            $table->unsignedTinyInteger('sample_integration_score')->nullable()->after('dj_profile_id');
            $table->unsignedTinyInteger('blending_score')->nullable()->after('track_selection_score');
            $table->unsignedTinyInteger('technical_execution_score')->nullable()->after('blending_score');
            $table->unsignedTinyInteger('battle_composition_score')->nullable()->after('technical_execution_score');
            $table->unsignedTinyInteger('entertainment_value_score')->nullable()->after('battle_composition_score');
            $table->unsignedTinyInteger('overall_performance_score')->nullable()->after('entertainment_value_score');
        });
    }

    public function down(): void
    {
        Schema::table('dj_battle_vote_scores', function (Blueprint $table): void {
            $table->dropColumn([
                'sample_integration_score',
                'blending_score',
                'technical_execution_score',
                'battle_composition_score',
                'entertainment_value_score',
                'overall_performance_score',
            ]);
        });
    }
};
