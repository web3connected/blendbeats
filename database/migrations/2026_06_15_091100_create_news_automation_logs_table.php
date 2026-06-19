<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('news_automation_logs')) {
            Schema::create('news_automation_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('workflow_name')->nullable()->index();
                $table->foreignId('rule_id')->nullable()->constrained('news_automation_rules')->nullOnDelete();
                $table->foreignId('event_id')->nullable()->constrained('news_events')->nullOnDelete();
                $table->string('status')->index();
                $table->text('message')->nullable();
                $table->json('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['rule_id', 'status'], 'news_automation_logs_rule_status_index');
                $table->index(['event_id', 'status'], 'news_automation_logs_event_status_index');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_automation_logs');
    }
};
