<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_activity_events', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->char('visitor_key', 64)->nullable()->index();
            $table->char('session_id_hash', 64)->nullable()->index();
            $table->char('ip_hash', 64)->nullable();
            $table->string('method', 10);
            $table->string('path', 512)->index();
            $table->string('route_name')->nullable()->index();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('referrer_host')->nullable()->index();
            $table->text('referrer_url')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('device_type', 20)->nullable()->index();
            $table->boolean('is_bot')->default(false)->index();
            $table->boolean('is_ajax')->default(false);
            $table->timestamps();

            $table->index(['occurred_at', 'path']);
            $table->index(['occurred_at', 'user_id']);
            $table->index(['occurred_at', 'admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_activity_events');
    }
};
