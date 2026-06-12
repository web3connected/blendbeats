<?php

use App\Models\MediaFile;
use App\Models\Mix;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$validMediaIds = MediaFile::query()
    ->where('collection', 'dj_media')
    ->whereNotNull('user_id')
    ->get()
    ->filter(function (MediaFile $file): bool {
        $portfolio = $file->metadata['portfolio'] ?? [];

        return $file->isAudio()
            && ($portfolio['visibility'] ?? null) === 'public'
            && in_array($portfolio['media_kind'] ?? 'mix', ['mix', 'track'], true);
    })
    ->pluck('id')
    ->all();

$staleCount = Mix::query()
    ->whereNotNull('audio_media_file_id')
    ->whereNotIn('audio_media_file_id', $validMediaIds)
    ->update([
        'is_public' => false,
        'is_featured' => false,
        'published_at' => null,
    ]);

$duplicateCount = 0;

Mix::query()
    ->whereNotNull('audio_media_file_id')
    ->where('is_public', true)
    ->get()
    ->groupBy(fn (Mix $mix): string => $mix->user_id.'|'.Str::lower(trim($mix->title)))
    ->each(function (Collection $mixes) use (&$duplicateCount): void {
        if ($mixes->count() <= 1) {
            return;
        }

        $keep = $mixes
            ->sortByDesc(fn (Mix $mix): int => (int) $mix->audio_media_file_id)
            ->first();

        $duplicateIds = $mixes
            ->pluck('id')
            ->reject(fn (int $id): bool => $id === $keep->id)
            ->values();

        if ($duplicateIds->isEmpty()) {
            return;
        }

        $duplicateCount += Mix::query()
            ->whereIn('id', $duplicateIds->all())
            ->update([
                'is_public' => false,
                'is_featured' => false,
                'published_at' => null,
            ]);
    });

$publicMixes = Mix::query()
    ->where('is_public', true)
    ->orderBy('title')
    ->get(['id', 'title', 'audio_media_file_id', 'play_count']);

echo "Stale media-backed mixes unpublished: {$staleCount}".PHP_EOL;
echo "Duplicate media-backed mixes unpublished: {$duplicateCount}".PHP_EOL;
echo "Current public mixes: {$publicMixes->count()}".PHP_EOL;

foreach ($publicMixes as $mix) {
    echo "#{$mix->id} {$mix->title} media={$mix->audio_media_file_id} plays={$mix->play_count}".PHP_EOL;
}
