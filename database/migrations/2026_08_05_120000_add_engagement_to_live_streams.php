<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table): void {
            $table->unsignedBigInteger('views_count')->default(0)->after('recording_storage_path');
        });

        Schema::create('live_stream_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_stream_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['live_stream_id', 'user_id']);
        });

        Schema::create('live_stream_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_stream_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_comments');
        Schema::dropIfExists('live_stream_likes');

        Schema::table('live_streams', function (Blueprint $table): void {
            $table->dropColumn('views_count');
        });
    }
};
