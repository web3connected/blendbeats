<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DjScratchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:120'],
        ]);

        if (! Schema::hasTable('media_files') || ! Schema::hasTable('dj_profiles')) {
            return response()->json($this->emptyPayload());
        }

        $scratches = MediaFile::query()
            ->with(['user.djProfile'])
            ->where('collection', 'dj_media')
            ->where('mime_type', 'like', 'video/%')
            ->latest('created_at')
            ->limit(120)
            ->get()
            ->filter(fn (MediaFile $file): bool => $this->isPublicScratch($file))
            ->filter(fn (MediaFile $file): bool => $this->matchesGenre($file, $filters['genre'] ?? null))
            ->filter(fn (MediaFile $file): bool => $this->matchesSearch($file, $filters['search'] ?? null))
            ->values();

        return response()->json([
            'scratches' => $scratches->map(fn (MediaFile $file): array => $this->scratchPayload($file))->values(),
            'stats' => [
                'scratch_count' => $scratches->count(),
                'dj_count' => $scratches->pluck('user_id')->filter()->unique()->count(),
                'genre_count' => $scratches
                    ->map(fn (MediaFile $file): ?string => $file->metadata['portfolio']['genre'] ?? null)
                    ->filter()
                    ->unique()
                    ->count(),
            ],
            'genres' => $scratches
                ->map(fn (MediaFile $file): ?string => $file->metadata['portfolio']['genre'] ?? null)
                ->filter()
                ->unique()
                ->sort()
                ->values(),
        ]);
    }

    private function isPublicScratch(MediaFile $file): bool
    {
        $metadata = $file->metadata ?? [];
        $portfolio = $metadata['portfolio'] ?? [];
        $duration = $this->durationSeconds($file);
        $isYoutubeLink = ($portfolio['source_type'] ?? $metadata['external_source']['provider'] ?? null) === 'youtube';
        $profile = $file->user?->djProfile;

        return $file->isVideo()
            && ($portfolio['visibility'] ?? null) === 'public'
            && ($portfolio['media_kind'] ?? null) === 'scratch'
            && ($isYoutubeLink || ($duration > 0 && floor($duration) <= 300))
            && $profile
            && $profile->visibility === 'public'
            && $profile->profile_status === 'active';
    }

    private function matchesGenre(MediaFile $file, ?string $genre): bool
    {
        if (! $genre) {
            return true;
        }

        return strcasecmp((string) ($file->metadata['portfolio']['genre'] ?? ''), $genre) === 0;
    }

    private function matchesSearch(MediaFile $file, ?string $search): bool
    {
        $query = trim((string) $search);

        if ($query === '') {
            return true;
        }

        $profile = $file->user?->djProfile;
        $portfolio = $file->metadata['portfolio'] ?? [];
        $haystack = strtolower(collect([
            $portfolio['title'] ?? null,
            $portfolio['description'] ?? null,
            $portfolio['genre'] ?? null,
            $file->original_name,
            $file->name,
            $profile?->dj_name,
            $profile?->handle,
        ])->filter()->join(' '));

        return str_contains($haystack, strtolower($query));
    }

    private function scratchPayload(MediaFile $file): array
    {
        $metadata = $file->metadata ?? [];
        $portfolio = $metadata['portfolio'] ?? [];
        $externalSource = $metadata['external_source'] ?? [];
        $profile = $file->user?->djProfile;
        $duration = $this->durationSeconds($file);

        return [
            'id' => (int) $file->id,
            'title' => $portfolio['title'] ?? $file->original_name ?? $file->name,
            'description' => $portfolio['description'] ?? null,
            'genre' => $portfolio['genre'] ?? null,
            'url' => $file->url,
            'cover_image_url' => $portfolio['cover_image_url'] ?? $externalSource['thumbnail_url'] ?? null,
            'source_type' => $portfolio['source_type'] ?? ($externalSource ? 'youtube' : 'upload'),
            'external_provider' => $portfolio['external_provider'] ?? $externalSource['provider'] ?? null,
            'external_url' => $portfolio['external_url'] ?? $externalSource['watch_url'] ?? null,
            'embed_url' => $portfolio['embed_url'] ?? $externalSource['embed_url'] ?? null,
            'thumbnail_url' => $portfolio['thumbnail_url'] ?? $externalSource['thumbnail_url'] ?? null,
            'mime_type' => $file->mime_type,
            'duration_seconds' => $duration,
            'formatted_size' => $file->formatted_size,
            'created_at' => $file->created_at?->toISOString(),
            'dj' => [
                'id' => $profile?->id,
                'name' => $profile?->dj_name ?? $file->user?->name ?? 'BlendBeats DJ',
                'handle' => $profile?->handle,
                'headline' => $profile?->profile_headline,
                'avatar_url' => $file->user?->getAvatarUrl(),
            ],
        ];
    }

    private function durationSeconds(MediaFile $file): float
    {
        return (float) (
            $file->metadata['portfolio']['duration_seconds']
            ?? $file->metadata['duration_seconds']
            ?? 0
        );
    }

    private function emptyPayload(): array
    {
        return [
            'scratches' => [],
            'stats' => [
                'scratch_count' => 0,
                'dj_count' => 0,
                'genre_count' => 0,
            ],
            'genres' => [],
        ];
    }
}
