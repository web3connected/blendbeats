<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DjFeaturedStatus;
use App\Models\DjGenre;
use App\Models\DjProfile;
use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DjHubController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:80'],
            'dj_type' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:255'],
            'bookings' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:featured,new,followers,name'],
        ]);

        $profiles = DjProfile::query()
            ->with(['user:id,name,email,avatar,is_gravatar,use_gravatar', 'genres', 'bookingSetting'])
            ->withCount('followers')
            ->where('visibility', 'public')
            ->where('profile_status', 'active')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('dj_name', 'like', "%{$search}%")
                        ->orWhere('profile_headline', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhereHas('genres', fn (Builder $genreQuery) => $genreQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['genre'] ?? null, function (Builder $query, string $genre): void {
                $query->whereHas('genres', fn (Builder $genreQuery) => $genreQuery->where('name', $genre));
            })
            ->when($filters['dj_type'] ?? null, fn (Builder $query, string $djType) => $query->where('dj_type', $djType))
            ->when($filters['location'] ?? null, function (Builder $query, string $location): void {
                $query->where(function (Builder $locationQuery) use ($location): void {
                    $locationQuery
                        ->where('city', 'like', "%{$location}%")
                        ->orWhere('state', 'like', "%{$location}%")
                        ->orWhere('country', 'like', "%{$location}%");
                });
            })
            ->when($request->boolean('bookings'), fn (Builder $query) => $query->where('booking_enabled', true));

        match ($filters['sort'] ?? 'featured') {
            'new' => $profiles->latest('published_at')->latest('created_at'),
            'followers' => $profiles->orderByDesc('followers_count'),
            'name' => $profiles->orderBy('dj_name'),
            default => $profiles
                ->orderByDesc(
                    DjFeaturedStatus::query()
                        ->selectRaw('count(*)')
                        ->whereColumn('dj_featured_status.dj_profile_id', 'dj_profiles.id')
                        ->where('status', 'active')
                )
                ->orderByDesc('followers_count')
                ->latest('published_at'),
        };

        $profileCollection = $profiles->limit(60)->get();
        $featuredMixes = $this->featuredMixesFor($profileCollection->pluck('user_id')->all());
        $featuredStatuses = $this->featuredStatusesFor($profileCollection->pluck('id')->all());

        return response()->json([
            'djs' => $profileCollection
                ->map(fn (DjProfile $profile): array => $this->directoryPayload(
                    $profile,
                    $featuredMixes[$profile->user_id] ?? null,
                    $featuredStatuses[$profile->id] ?? [],
                ))
                ->values(),
            'featured_djs' => $this->featuredDjs(),
            'filters' => [
                'genres' => DjGenre::query()->where('is_active', true)->orderBy('name')->pluck('name')->values(),
                'dj_types' => DjProfile::query()
                    ->whereNotNull('dj_type')
                    ->where('dj_type', '!=', '')
                    ->distinct()
                    ->orderBy('dj_type')
                    ->pluck('dj_type')
                    ->values(),
            ],
        ]);
    }

    public function show(string $handle): JsonResponse
    {
        $profile = DjProfile::query()
            ->with(['user:id,name,email,avatar,is_gravatar,use_gravatar', 'genres', 'bookingSetting', 'socialLinks'])
            ->withCount('followers')
            ->where('visibility', 'public')
            ->where('profile_status', 'active')
            ->where('handle', $handle)
            ->firstOrFail();

        return response()->json([
            'dj' => $this->directoryPayload(
                $profile,
                $this->featuredMixesFor([$profile->user_id])[$profile->user_id] ?? null,
                $this->featuredStatusesFor([$profile->id])[$profile->id] ?? [],
            ),
        ]);
    }

    private function directoryPayload(DjProfile $profile, ?MediaFile $featuredMix, array $featuredStatuses): array
    {
        $primaryGenre = $profile->genres->firstWhere('pivot.is_primary', true);
        $secondaryGenres = $profile->genres
            ->filter(fn (DjGenre $genre): bool => ! (bool) $genre->pivot->is_primary)
            ->pluck('name')
            ->values();

        return [
            'id' => $profile->id,
            'dj_name' => $profile->dj_name,
            'handle' => $profile->handle,
            'headline' => $profile->profile_headline,
            'bio' => $profile->bio,
            'avatar_url' => $profile->user?->getAvatarUrl(256),
            'primary_genre' => $primaryGenre?->name,
            'secondary_genres' => $secondaryGenres,
            'dj_type' => $profile->dj_type,
            'location' => $this->formatLocation($profile),
            'city' => $profile->city,
            'state' => $profile->state,
            'country' => $profile->country,
            'open_for_bookings' => (bool) $profile->booking_enabled,
            'followers_count' => (int) ($profile->followers_count ?? 0),
            'featured_statuses' => $featuredStatuses,
            'featured_mix' => $featuredMix ? [
                'id' => $featuredMix->id,
                'title' => $featuredMix->name,
                'url' => $featuredMix->url,
                'mime_type' => $featuredMix->mime_type,
            ] : null,
        ];
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, MediaFile>
     */
    private function featuredMixesFor(array $userIds): array
    {
        return MediaFile::query()
            ->whereIn('user_id', $userIds)
            ->where('collection', 'dj_media')
            ->where('mime_type', 'like', 'audio/%')
            ->latest()
            ->get()
            ->unique('user_id')
            ->keyBy('user_id')
            ->all();
    }

    /**
     * @param array<int, int> $profileIds
     * @return array<int, array<int, string>>
     */
    private function featuredStatusesFor(array $profileIds): array
    {
        return DjFeaturedStatus::query()
            ->whereIn('dj_profile_id', $profileIds)
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->get()
            ->groupBy('dj_profile_id')
            ->map(fn ($statuses) => $statuses->pluck('featured_type')->values()->all())
            ->all();
    }

    private function featuredDjs(): array
    {
        $activeFeaturedStatuses = DjFeaturedStatus::query()
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->latest('start_date')
            ->latest('id')
            ->limit(16)
            ->get();

        $profileIds = $activeFeaturedStatuses
            ->pluck('dj_profile_id')
            ->unique()
            ->take(4)
            ->values()
            ->all();

        if (empty($profileIds)) {
            return [];
        }

        $featuredMixes = $this->featuredMixesFor(
            DjProfile::query()
                ->whereIn('id', $profileIds)
                ->pluck('user_id')
                ->all()
        );

        $featuredStatuses = $this->featuredStatusesFor($profileIds);

        $profiles = DjProfile::query()
            ->with(['user:id,name,email,avatar,is_gravatar,use_gravatar', 'genres', 'bookingSetting'])
            ->withCount('followers')
            ->whereIn('id', $profileIds)
            ->where('visibility', 'public')
            ->where('profile_status', 'active')
            ->get()
            ->keyBy('id');

        return collect($profileIds)
            ->map(fn (int $profileId) => $profiles->get($profileId))
            ->filter()
            ->map(fn (DjProfile $profile): array => $this->directoryPayload(
                $profile,
                $featuredMixes[$profile->user_id] ?? null,
                $featuredStatuses[$profile->id] ?? [],
            ))
            ->values()
            ->all();
    }

    private function formatLocation(DjProfile $profile): string
    {
        return collect([$profile->city, $profile->state, $profile->country])
            ->filter()
            ->join(', ');
    }
}
