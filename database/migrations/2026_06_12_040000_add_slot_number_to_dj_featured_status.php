<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->unsignedTinyInteger('slot_number')->nullable()->after('dj_profile_id');
            $table->unsignedTinyInteger('rotation_weight')->default(1)->after('featured_type');
        });

        DB::table('dj_featured_status')
            ->whereNull('slot_number')
            ->orderBy('id')
            ->get(['id'])
            ->values()
            ->each(function (object $row, int $index): void {
                DB::table('dj_featured_status')
                    ->where('id', $row->id)
                    ->update(['slot_number' => ($index % 24) + 1]);
            });

        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->index(['slot_number', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->dropIndex(['slot_number', 'status']);
            $table->dropColumn(['slot_number', 'rotation_weight']);
        });
    }
};
