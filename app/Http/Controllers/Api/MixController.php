<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mix;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class MixController extends Controller
{
    public function index(): JsonResponse
    {
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
}
