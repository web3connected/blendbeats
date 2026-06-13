<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'media_storage_tier')) {
            return;
        }

        DB::table('users')
            ->where('media_storage_tier', 'starter')
            ->update(['media_storage_tier' => config('billing.subscription.free_tier', 'free')]);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY media_storage_tier VARCHAR(255) NOT NULL DEFAULT 'free'");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'media_storage_tier') && DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY media_storage_tier VARCHAR(255) NOT NULL DEFAULT 'starter'");
        }
    }
};
