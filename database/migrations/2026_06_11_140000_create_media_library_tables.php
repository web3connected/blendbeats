<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->unique()->constrained('admins')->cascadeOnDelete();
            $table->string('account_slug')->unique();
            $table->string('disk')->default('public');
            $table->string('root_path');
            $table->string('storage_tier')->default('free');
            $table->unsignedBigInteger('storage_limit_bytes')->default(0);
            $table->unsignedBigInteger('storage_used_bytes')->default(0);
            $table->enum('status', ['active', 'suspended', 'disabled'])->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('media_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('media_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('original_name')->nullable();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('collection')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['disk', 'collection']);
            $table->index(['user_id', 'created_at']);
            $table->index(['admin_id', 'created_at']);
            $table->unique(['disk', 'path']);
        });

        Schema::create('media_manager_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action');
            $table->string('disk');
            $table->string('file_path');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['disk', 'created_at']);
        });

        Schema::create('user_feature_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->cascadeOnDelete();
            $table->string('feature_key');
            $table->enum('status', ['active', 'paused', 'disabled'])->default('active');
            $table->string('source')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'feature_key']);
            $table->unique(['admin_id', 'feature_key']);
            $table->index(['feature_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feature_activations');
        Schema::dropIfExists('media_manager_audit_logs');
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('media_accounts');
    }
};
