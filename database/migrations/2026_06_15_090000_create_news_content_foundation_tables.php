<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('group')->nullable()->index();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['name', 'group']);
            });
        }

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type')->nullable()->index();
                $table->string('group')->nullable()->index();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['name', 'group']);
            });
        }

        if (! Schema::hasTable('news_sources')) {
            Schema::create('news_sources', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('url')->nullable();
                $table->string('source_type')->default('internal')->index();
                $table->string('credibility_rating')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('news_events')) {
            Schema::create('news_events', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('event_type')->nullable()->index();
                $table->string('status')->default('active')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->foreignId('news_source_id')->nullable()->constrained('news_sources')->nullOnDelete();
                $table->foreignId('news_event_id')->nullable()->constrained('news_events')->nullOnDelete();
                $table->string('content_type')->default('blog')->index();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('excerpt')->nullable();
                $table->longText('content');
                $table->string('status')->default('draft')->index();
                $table->boolean('is_verified')->default(false)->index();
                $table->string('verification_status')->default('unverified')->index();
                $table->boolean('is_breaking')->default(false)->index();
                $table->boolean('is_featured')->default(false)->index();
                $table->unsignedTinyInteger('importance_level')->default(1)->index();
                $table->json('featured_image')->nullable();
                $table->json('seo')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['content_type', 'status', 'published_at']);
                $table->index(['content_type', 'is_featured', 'published_at']);
                $table->index(['content_type', 'is_breaking', 'published_at']);
            });
        }

        if (! Schema::hasTable('category_post')) {
            Schema::create('category_post', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['category_id', 'post_id']);
            });
        }

        if (! Schema::hasTable('taggables')) {
            Schema::create('taggables', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->morphs('taggable');
                $table->timestamps();

                $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
            });
        }

        if (! Schema::hasTable('comments')) {
            Schema::create('comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
                $table->string('author_name')->nullable();
                $table->text('content');
                $table->string('status')->default('pending')->index();
                $table->timestamp('approved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['post_id', 'status', 'created_at']);
            });
        }

        if (! Schema::hasTable('news_milestones')) {
            Schema::create('news_milestones', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('news_event_id')->constrained('news_events')->cascadeOnDelete();
                $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('milestone_type')->nullable()->index();
                $table->timestamp('occurred_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('news_story_relations')) {
            Schema::create('news_story_relations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->foreignId('related_post_id')->constrained('posts')->cascadeOnDelete();
                $table->string('relation_type')->default('related')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['post_id', 'related_post_id', 'relation_type'], 'news_story_relation_unique');
            });
        }

        if (! Schema::hasTable('news_trending_metrics')) {
            Schema::create('news_trending_metrics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->unsignedBigInteger('views')->default(0);
                $table->unsignedBigInteger('shares')->default(0);
                $table->unsignedBigInteger('comments_count')->default(0);
                $table->unsignedBigInteger('engagement_score')->default(0)->index();
                $table->timestamp('window_started_at')->nullable()->index();
                $table->timestamp('window_ended_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['post_id', 'window_started_at', 'window_ended_at'], 'news_trending_post_window_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_trending_metrics');
        Schema::dropIfExists('news_story_relations');
        Schema::dropIfExists('news_milestones');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('category_post');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('news_events');
        Schema::dropIfExists('news_sources');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
