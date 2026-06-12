<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dj_lounge_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->enum('type', ['text', 'mix_update', 'battle_callout', 'question'])->default('text');
            $table->enum('status', ['published', 'hidden', 'archived'])->default('published');
            $table->enum('visibility', ['public', 'followers', 'private'])->default('public');
            $table->string('genre')->nullable();
            $table->string('media_title')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_meta')->nullable();
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('repost_count')->default(0);
            $table->unsignedInteger('bookmark_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility', 'published_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('dj_lounge_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dj_lounge_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('dj_lounge_comments')
                ->cascadeOnDelete();
            $table->text('body');
            $table->enum('status', ['visible', 'hidden', 'flagged'])->default('visible');
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('reply_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dj_lounge_post_id', 'created_at']);
            $table->index(['parent_id', 'created_at']);
        });

        Schema::create('dj_lounge_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reactable');
            $table->enum('type', ['like', 'dislike'])->default('like');
            $table->timestamps();

            $table->unique(['user_id', 'reactable_type', 'reactable_id']);
        });

        Schema::create('dj_lounge_reposts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dj_lounge_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 280)->nullable();
            $table->timestamps();

            $table->unique(['dj_lounge_post_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('dj_lounge_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dj_lounge_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['dj_lounge_post_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('dj_lounge_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reportable');
            $table->enum('reason', ['spam', 'harassment', 'copyright', 'explicit', 'impersonation', 'other']);
            $table->text('details')->nullable();
            $table->enum('status', ['open', 'reviewing', 'resolved', 'dismissed'])->default('open');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['reporter_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dj_lounge_reports');
        Schema::dropIfExists('dj_lounge_bookmarks');
        Schema::dropIfExists('dj_lounge_reposts');
        Schema::dropIfExists('dj_lounge_reactions');
        Schema::dropIfExists('dj_lounge_comments');
        Schema::dropIfExists('dj_lounge_posts');
    }
};
