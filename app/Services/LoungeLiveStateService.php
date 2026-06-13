<?php

namespace App\Services;

use App\Models\LoungePlaylistTrack;
use App\Models\MediaFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LoungeLiveStateService
{
    public function state(): array
    {
        $now = now();
        $playlist = $this->playlist();
        $playlistDuration = (int) $playlist->sum('duration');

        if ($playlist->isEmpty() || $playlistDuration <= 0) {
            return [
                'current_track' => null,
                'next_track' => null,
                'playlist' => [],
                'current_position_seconds' => 0,
                'server_time' => $now->toISOString(),
                'playlist_version' => 'empty',
                'mode' => 'lounge_live',
            ];
        }

        $playlistPosition = $now->timestamp % $playlistDuration;
        $elapsed = 0;
        $currentIndex = 0;

        foreach ($playlist as $index => $track) {
            $duration = (int) $track['duration'];

            if ($playlistPosition < $elapsed + $duration) {
                $currentIndex = $index;
                break;
            }

            $elapsed += $duration;
        }

        $currentTrack = $playlist[$currentIndex];
        $nextTrack = $playlist[($currentIndex + 1) % $playlist->count()];

        return [
            'current_track' => $currentTrack,
            'next_track' => $nextTrack,
            'playlist' => $playlist->values()->all(),
            'current_position_seconds' => max(0, $playlistPosition - $elapsed),
            'server_time' => $now->toISOString(),
            'playlist_version' => sha1($playlist->pluck('version_key')->implode('|')),
            'mode' => 'lounge_live',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function playlist(): Collection
    {
        if (! Schema::hasTable('lounge_playlist_tracks')) {
            return collect();
        }

        $fallbackDuration = max(1, (int) config('lounge.live.fallback_track_duration_seconds', 300));

        return LoungePlaylistTrack::query()
            ->active()
            ->with('mediaFile.user:id,name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (LoungePlaylistTrack $track): bool => $this->isPublicPlayableMedia($track->mediaFile))
            ->map(function (LoungePlaylistTrack $track) use ($fallbackDuration): array {
                $file = $track->mediaFile;
                $portfolio = $file->metadata['portfolio'] ?? [];
                $title = filled($portfolio['title'] ?? null)
                    ? (string) $portfolio['title']
                    : (string) ($file->original_name ?? $file->name);
                $duration = (int) ($file->metadata['duration'] ?? $file->metadata['duration_seconds'] ?? $fallbackDuration);

                return [
                    'id' => 'lounge-media-'.$file->id,
                    'media_file_id' => $file->id,
                    'title' => $title,
                    'artist' => $file->user?->name ?: 'BlendBeats DJ',
                    'src' => $file->url,
                    'artwork' => null,
                    'genre' => $portfolio['genre'] ?? null,
                    'duration' => max(1, $duration),
                    'featured' => $track->is_featured,
                    'version_key' => implode(':', [
                        $track->id,
                        $file->id,
                        $track->sort_order,
                        (int) $track->is_active,
                        $track->updated_at?->timestamp ?? 0,
                        $file->updated_at?->timestamp ?? 0,
                    ]),
                ];
            })
            ->values();
    }

    private function isPublicPlayableMedia(?MediaFile $file): bool
    {
        if (! $file || ! $file->isAudio() || $file->collection !== 'dj_media') {
            return false;
        }

        $portfolio = $file->metadata['portfolio'] ?? [];

        return ($portfolio['visibility'] ?? null) === 'public'
            && in_array($portfolio['media_kind'] ?? 'mix', ['mix', 'track'], true);
    }
}
