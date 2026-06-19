<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('news_automation_rules')) {
            Schema::create('news_automation_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('rule_type')->index();
                $table->string('event_type')->nullable()->index();
                $table->string('milestone_key')->nullable()->index();
                $table->string('source_type')->nullable()->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->string('condition_field')->nullable();
                $table->string('condition_operator')->nullable();
                $table->string('condition_value')->nullable();
                $table->unsignedInteger('cooldown_minutes')->default(60);
                $table->unsignedInteger('priority')->default(1)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'priority'], 'news_automation_rules_active_priority_index');
                $table->index(['rule_type', 'is_active'], 'news_automation_rules_type_active_index');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_automation_rules');
    }
};
