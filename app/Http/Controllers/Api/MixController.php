<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\Mix;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MixController extends Controller
{
    public function index(): JsonResponse
    {
        $this->syncPublicPortfolioMediaToMixes();

        $publicMixes = Mix::query()
            ->public()
            ->with('user:id,name')
            ->latestPublished()
            ->get();

        $featuredMixes = $publicMixes
            ->where('is_featured', true)
            ->sortByDesc('published_at')
            ->values();

        return response()->json([
            'stats' => $this->stats($publicMixes, $featuredMixes),
            'featured' => $featuredMixes->map(fn (Mix $mix): array => $this->mixPayload($mix))->values(),
            'mixes' => $publicMixes->map(fn (Mix $mix): array => $this->mixPayload($mix))->values(),
            'genres' => $this->genreRows($publicMixes),
        ]);
    }

    public function play(Mix $mix): JsonResponse
    {
        abort_unless($mix->is_public && $mix->published_at, 404);

        $mix->incrementPlayCount();

        return response()->json([
            'play_count' => $mix->refresh()->play_count,
        ]);
    }

    /**
     * @param Collection<int, Mix> $publicMixes
     * @param Collection<int, Mix> $featuredMixes
     */
    private function stats(Collection $publicMixes, Collection $featuredMixes): array
    {
        $ratedMixes = $publicMixes->filter(fn (Mix $mix): bool => $mix->rating_count > 0);

        return [
            'featured_mixes' => $featuredMixes->count(),
            'total_plays' => $publicMixes->sum('play_count'),
            'average_rating' => round((float) $ratedMixes->avg('rating_average'), 1),
            'genre_count' => $publicMixes->pluck('genre')->filter()->unique()->count(),
        ];
    }

    /**
     * @param Collection<int, Mix> $publicMixes
     */
    private function genreRows(Collection $publicMixes): array
    {
        return $publicMixes
            ->filter(fn (Mix $mix): bool => filled($mix->genre))
            ->groupBy('genre')
            ->map(fn (Collection $mixes, string $genre): array => [
                'genre' => $genre,
                'mixes' => $mixes
                    ->sortByDesc('play_count')
                    ->take(6)
                    ->map(fn (Mix $mix): array => $this->mixPayload($mix))
                    ->values(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function mixPayload(Mix $mix): array
    {
        return [
            'id' => $mix->id,
            'title' => $mix->title,
            'slug' => $mix->slug,
            'description' => $mix->description,
            'genre' => $mix->genre,
            'audio_url' => $mix->audio_url,
            'cover_image_url' => $mix->cover_image_url,
            'duration' => $mix->duration,
            'is_featured' => $mix->is_featured,
            'play_count' => $mix->play_count,
            'rating_average' => (float) $mix->rating_average,
            'rating_count' => $mix->rating_count,
            'published_at' => $mix->published_at?->toISOString(),
            'created_at' => $mix->created_at?->toISOString(),
            'dj' => [
                'id' => $mix->user?->id,
                'name' => $mix->dj_name,
            ],
        ];
    }

    private function syncPublicPortfolioMediaToMixes(): void
    {
        $publicPortfolioMediaIds = MediaFile::query()
            ->with('user:id,name')
            ->where('collection', 'dj_media')
            ->whereNotNull('user_id')
            ->latest('created_at')
            ->get()
            ->filter(function (MediaFile $file): bool {
                $portfolio = $file->metadata['portfolio'] ?? [];

                return $file->isAudio()
                    && ($portfolio['visibility'] ?? null) === 'public'
                    && in_array($portfolio['media_kind'] ?? 'mix', ['mix', 'track'], true);
            });

        Mix::query()
            ->whereNotNull('audio_media_file_id')
            ->whereNotIn('audio_media_file_id', $publicPortfolioMediaIds->pluck('id')->all())
            ->update([
                'is_public' => false,
                'is_featured' => false,
                'published_at' => null,
            ]);

        $publicPortfolioMediaIds->each(function (MediaFile $file): void {
                $portfolio = $file->metadata['portfolio'] ?? [];
                $title = filled($portfolio['title'] ?? null)
                    ? (string) $portfolio['title']
                    : (string) ($file->original_name ?? $file->name);

                Mix::query()->updateOrCreate(
                    ['audio_media_file_id' => $file->id],
                    [
                        'user_id' => $file->user_id,
                        'title' => $title,
                        'slug' => $this->mixSlugForMediaFile($file, $title),
                        'description' => $portfolio['description'] ?? null,
                        'genre' => $portfolio['genre'] ?? null,
                        'audio_file' => $file->path,
                        'is_public' => true,
                        'published_at' => $file->created_at ?? now(),
                    ],
                );
            });

        $this->unpublishDuplicatePortfolioMixes();
    }

    private function unpublishDuplicatePortfolioMixes(): void
    {
        Mix::query()
            ->whereNotNull('audio_media_file_id')
            ->where('is_public', true)
            ->get()
            ->groupBy(fn (Mix $mix): string => $mix->user_id.'|'.Str::lower(trim($mix->title)))
            ->each(function (Collection $mixes): void {
                if ($mixes->count() <= 1) {
                    return;
                }

                $keep = $mixes
                    ->sortByDesc(fn (Mix $mix): int => (int) $mix->audio_media_file_id)
                    ->first();

                Mix::query()
                    ->whereIn('id', $mixes->pluck('id')->reject(fn (int $id): bool => $id === $keep->id)->all())
                    ->update([
                        'is_public' => false,
                        'is_featured' => false,
                        'published_at' => null,
                    ]);
            });
    }

    private function mixSlugForMediaFile(MediaFile $file, string $title): string
    {
        $base = Str::slug($title) ?: 'mix';
        $slug = $base;
        $index = 2;

        while (
            Mix::query()
                ->where('slug', $slug)
                ->where('audio_media_file_id', '!=', $file->id)
                ->exists()
        ) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }
}
