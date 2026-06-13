<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('rateable');
            $table->unsignedTinyInteger('rating');
            $table->text('review')->nullable();
            $table->string('context')->default('default');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'rateable_type', 'rateable_id', 'context'], 'ratings_user_rateable_context_unique');
            $table->index(['rateable_type', 'rateable_id', 'context', 'created_at'], 'ratings_rateable_context_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
