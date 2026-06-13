<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lounge_playlist_tracks') || ! Schema::hasTable('media_files')) {
            return;
        }

        $now = now();
        $nextOrder = (int) DB::table('lounge_playlist_tracks')->max('sort_order');

        DB::table('media_files')
            ->select(['id', 'mime_type', 'collection', 'metadata'])
            ->where('collection', 'dj_media')
            ->whereNotNull('user_id')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->chunkById(100, function ($files) use (&$nextOrder, $now): void {
                foreach ($files as $file) {
                    $metadata = json_decode((string) $file->metadata, true) ?: [];
                    $portfolio = $metadata['portfolio'] ?? [];

                    $isPublicPlayable = str_starts_with((string) $file->mime_type, 'audio/')
                        && ($portfolio['visibility'] ?? null) === 'public'
                        && in_array($portfolio['media_kind'] ?? 'mix', ['mix', 'track'], true);

                    if (! $isPublicPlayable) {
                        continue;
                    }

                    DB::table('lounge_playlist_tracks')->updateOrInsert(
                        ['media_file_id' => $file->id],
                        [
                            'sort_order' => ++$nextOrder,
                            'is_active' => true,
                            'is_featured' => false,
                            'approved_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                }
            });
    }

    public function down(): void
    {
        //
    }
};
