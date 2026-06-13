<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'use_gravatar')) {
                $table->boolean('use_gravatar')->default(true)->after('avatar');
            }

            if (! Schema::hasColumn('users', 'is_gravatar')) {
                $table->boolean('is_gravatar')->default(false)->after('use_gravatar');
            }

            if (! Schema::hasColumn('users', 'media_storage_tier')) {
                $table->string('media_storage_tier')->default('free')->after('is_gravatar');
            }
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $nameParts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'first_name' => $nameParts[0] ?? null,
                            'last_name' => $nameParts[1] ?? null,
                        ]);
                }
            });

        if (Schema::hasColumn('users', 'use_gravatar') && Schema::hasColumn('users', 'is_gravatar')) {
            DB::table('users')->update([
                'is_gravatar' => DB::raw('use_gravatar'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['media_storage_tier', 'is_gravatar', 'use_gravatar', 'avatar', 'last_name', 'first_name'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
