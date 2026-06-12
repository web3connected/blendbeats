<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mixes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audio_media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->foreignId('cover_media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('genre')->nullable()->index();
            $table->string('audio_file')->nullable();
            $table->string('cover_image')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedBigInteger('play_count')->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['is_public', 'is_featured']);
            $table->index(['is_public', 'published_at']);
            $table->index(['genre', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mixes');
    }
};
