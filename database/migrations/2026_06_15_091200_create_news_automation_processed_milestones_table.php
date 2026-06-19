<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('news_automation_processed_milestones')) {
            Schema::create('news_automation_processed_milestones', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('rule_id')->nullable()->constrained('news_automation_rules')->nullOnDelete();
                $table->foreignId('event_id')->nullable()->constrained('news_events')->nullOnDelete();
                $table->string('milestone_key');
                $table->string('source_type')->nullable()->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
                $table->timestamp('processed_at')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['rule_id', 'event_id', 'milestone_key'], 'news_automation_processed_unique');
                $table->index(['source_type', 'source_id'], 'news_automation_processed_source_index');
                $table->index('milestone_key');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_automation_processed_milestones');
    }
};
