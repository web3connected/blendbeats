<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_gravatar')) {
                $table->boolean('is_gravatar')->default(false)->after('use_gravatar');
            }
        });

        if (Schema::hasColumn('users', 'use_gravatar')) {
            DB::table('users')->update([
                'is_gravatar' => DB::raw('use_gravatar'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_gravatar')) {
                $table->dropColumn('is_gravatar');
            }
        });
    }
};
