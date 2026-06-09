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
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_slug')->unique();
            $table->string('disk')->default('public');
            $table->string('root_path');
            $table->string('storage_tier')->default('starter');
            $table->unsignedBigInteger('storage_limit_bytes')->default(0);
            $table->unsignedBigInteger('storage_used_bytes')->default(0);
            $table->enum('status', ['active', 'suspended', 'disabled'])->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('user_feature_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key');
            $table->enum('status', ['active', 'paused', 'disabled'])->default('active');
            $table->string('source')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'feature_key']);
            $table->index(['feature_key', 'status']);
        });

        Schema::table('media_files', function (Blueprint $table): void {
            $table->foreignId('media_account_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('media_account_id');
        });

        Schema::dropIfExists('user_feature_activations');
        Schema::dropIfExists('media_accounts');
    }
};
