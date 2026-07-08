<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_duration_minutes')->nullable()->after('status');
            $table->boolean('recording_enabled')->default(false)->after('ended_at');
            $table->string('recording_status')->nullable()->after('recording_enabled');
            $table->timestamp('recording_started_at')->nullable()->after('recording_status');
            $table->timestamp('recording_ended_at')->nullable()->after('recording_started_at');
            $table->string('recording_storage_path')->nullable()->after('recording_ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn([
                'max_duration_minutes',
                'recording_enabled',
                'recording_status',
                'recording_started_at',
                'recording_ended_at',
                'recording_storage_path',
            ]);
        });
    }
};
