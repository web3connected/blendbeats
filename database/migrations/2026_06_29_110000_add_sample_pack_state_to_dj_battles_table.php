<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dj_battles', function (Blueprint $table): void {
            $table->string('sample_pack_status')->default('pending');
            $table->timestamp('sample_pack_ready_at')->nullable();
            $table->timestamp('sample_pack_bypassed_at')->nullable();
            $table->json('sample_pack_metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dj_battles', function (Blueprint $table): void {
            $table->dropColumn([
                'sample_pack_status',
                'sample_pack_ready_at',
                'sample_pack_bypassed_at',
                'sample_pack_metadata',
            ]);
        });
    }
};
