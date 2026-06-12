<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        if (! $this->hasDjHubTables()) {
            return response()->json($this->emptyPayload());
        }

        $profiles = $this->baseProfileQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('dj_profiles.dj_name', 'like', "%{$search}%")
                        ->orWhere('dj_profiles.profile_headline', 'like', "%{$search}%")
                        ->orWhere('dj_profiles.city', 'like', "%{$search}%")
                        ->orWhere('dj_profiles.state', 'like', "%{$search}%")
                        ->orWhere('dj_profiles.country', 'like', "%{$search}%");
                });
            })
            ->when($filters['genre'] ?? null, function (Builder $query, string $genre): void {
                $query->whereExists(function (Builder $genreQuery) use ($genre): void {
                    $genreQuery
                        ->selectRaw('1')
                        ->from('dj_profile_genres')
                        ->join('dj_genres', 'dj_genres.id', '=', 'dj_profile_genres.dj_genre_id')
                        ->whereColumn('dj_profile_genres.dj_profile_id', 'dj_profiles.id')
                        ->where('dj_genres.name', $genre);
                });
            })
            ->when($filters['dj_type'] ?? null, fn (Builder $query, string $djType) => $query->where('dj_profiles.dj_type', $djType))
            ->when($filters['location'] ?? null, function (Builder $query, string $location): void {
                $query->where(function (Builder $locationQuery) use ($location): void {
                    $locationQuery
                        ->where('dj_profiles.city', 'like', "%{$location}%")
                        ->orWhere('dj_profiles.state', 'like', "%{$location}%")
                        ->orWhere('dj_profiles.country', 'like', "%{$location}%");
                });
            })
            ->when($request->boolean('bookings'), fn (Builder $query) => $query->where('dj_profiles.booking_enabled', true));

        match ($filters['sort'] ?? 'featured') {
            'new' => $profiles->orderByDesc('dj_profiles.published_at')->orderByDesc('dj_profiles.created_at'),
            'followers' => $profiles->orderByDesc('followers_count'),
            'name' => $profiles->orderBy('dj_profiles.dj_name'),
            default => $profiles->orderByDesc('featured_count')->orderByDesc('followers_count')->orderByDesc('dj_profiles.published_at'),
        };

        $profileRows = $profiles->limit(60)->get();
        $featuredDjs = $this->featuredDjs();

        return response()->json([
            'djs' => $profileRows->map(fn ($profile): array => $this->profilePayload($profile))->values(),
            'featured_djs' => $featuredDjs,
            'filters' => $this->filters(),
        ]);
    }

    public function show(string $handle): JsonResponse
    {
        if (! $this->hasDjHubTables()) {
            abort(404);
        }

        $profile = $this->baseProfileQuery()
            ->where('dj_profiles.handle', $handle)
            ->first();

        abort_unless($profile, 404);

        return response()->json([
            'dj' => $this->profilePayload($profile),
        ]);
    }

    private function hasDjHubTables(): bool
    {
        return Schema::hasTable('dj_profiles')
            && Schema::hasTable('dj_genres')
            && Schema::hasTable('dj_profile_genres');
    }

    private function baseProfileQuery(): Builder
    {
        $followersSubquery = Schema::hasTable('dj_followers')
            ? DB::table('dj_followers')
                ->selectRaw('count(*)')
                ->whereColumn('dj_followers.followed_dj_id', 'dj_profiles.id')
            : DB::query()->selectRaw('0');

        $featuredSubquery = Schema::hasTable('dj_featured_status')
            ? DB::table('dj_featured_status')
                ->selectRaw('count(*)')
                ->whereColumn('dj_featured_status.dj_profile_id', 'dj_profiles.id')
                ->where('dj_featured_status.status', 'active')
            : DB::query()->selectRaw('0');

        return DB::table('dj_profiles')
            ->join('users', 'users.id', '=', 'dj_profiles.user_id')
            ->select([
                'dj_profiles.*',
                'users.name as user_name',
                'users.avatar as user_avatar',
            ])
            ->selectSub($followersSubquery, 'followers_count')
            ->selectSub($featuredSubquery, 'featured_count')
            ->where('dj_profiles.visibility', 'public')
            ->where('dj_profiles.profile_status', 'active');
    }

    private function profilePayload(object $profile): array
    {
        $genres = $this->genresFor((int) $profile->id);
        $primaryGenre = collect($genres)->firstWhere('is_primary', true)['name'] ?? null;
        $secondaryGenres = collect($genres)
            ->reject(fn (array $genre): bool => (bool) $genre['is_primary'])
            ->pluck('name')
            ->values()
            ->all();

        return [
            'id' => (int) $profile->id,
            'dj_name' => $profile->dj_name,
            'handle' => $profile->handle,
            'headline' => $profile->profile_headline ?? null,
            'bio' => $profile->bio ?? null,
            'avatar_url' => $this->avatarUrl($profile->user_avatar ?? null),
            'primary_genre' => $primaryGenre,
            'secondary_genres' => $secondaryGenres,
            'dj_type' => $profile->dj_type ?? null,
            'location' => collect([$profile->city ?? null, $profile->state ?? null, $profile->country ?? null])->filter()->join(', '),
            'city' => $profile->city ?? null,
            'state' => $profile->state ?? null,
            'country' => $profile->country ?? null,
            'open_for_bookings' => (bool) ($profile->booking_enabled ?? false),
            'followers_count' => (int) ($profile->followers_count ?? 0),
            'featured_statuses' => $this->featuredStatusesFor((int) $profile->id),
            'featured_mix' => $this->featuredMixFor((int) $profile->user_id),
        ];
    }

    private function genresFor(int $profileId): array
    {
        return DB::table('dj_profile_genres')
            ->join('dj_genres', 'dj_genres.id', '=', 'dj_profile_genres.dj_genre_id')
            ->where('dj_profile_genres.dj_profile_id', $profileId)
            ->orderByDesc('dj_profile_genres.is_primary')
            ->orderBy('dj_genres.name')
            ->get(['dj_genres.name', 'dj_profile_genres.is_primary'])
            ->map(fn ($genre): array => [
                'name' => $genre->name,
                'is_primary' => (bool) $genre->is_primary,
            ])
            ->all();
    }

    private function featuredStatusesFor(int $profileId): array
    {
        if (! Schema::hasTable('dj_featured_status')) {
            return [];
        }

        return DB::table('dj_featured_status')
            ->where('dj_profile_id', $profileId)
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('start_date')->orWhere('start_date', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->pluck('featured_type')
            ->values()
            ->all();
    }

    private function featuredMixFor(int $userId): ?array
    {
        if (! Schema::hasTable('media_files')) {
            return null;
        }

        $mix = DB::table('media_files')
            ->where('user_id', $userId)
            ->where('collection', 'dj_media')
            ->where('mime_type', 'like', 'audio/%')
            ->orderByDesc('created_at')
            ->first();

        if (! $mix) {
            return null;
        }

        return [
            'id' => (int) $mix->id,
            'title' => $mix->name,
            'url' => "/api/media/files/{$mix->id}/stream",
            'mime_type' => $mix->mime_type,
        ];
    }

    private function featuredDjs(): array
    {
        if (! Schema::hasTable('dj_featured_status')) {
            return [];
        }

        $profileIds = DB::table('dj_featured_status')
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('start_date')->orWhere('start_date', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->pluck('dj_profile_id')
            ->unique()
            ->take(4)
            ->values()
            ->all();

        if (empty($profileIds)) {
            return [];
        }

        return $this->baseProfileQuery()
            ->whereIn('dj_profiles.id', $profileIds)
            ->get()
            ->sortBy(fn ($profile) => array_search($profile->id, $profileIds, true))
            ->map(fn ($profile): array => $this->profilePayload($profile))
            ->values()
            ->all();
    }

    private function filters(): array
    {
        return [
            'genres' => DB::table('dj_genres')
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->values(),
            'dj_types' => DB::table('dj_profiles')
                ->whereNotNull('dj_type')
                ->where('dj_type', '!=', '')
                ->distinct()
                ->orderBy('dj_type')
                ->pluck('dj_type')
                ->values(),
        ];
    }

    private function avatarUrl(?string $avatar): ?string
    {
        if (! $avatar) {
            return null;
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://') || str_starts_with($avatar, '/')) {
            return $avatar;
        }

        if (str_starts_with($avatar, 'media/')) {
            return asset($avatar);
        }

        return asset('storage/'.$avatar);
    }

    private function emptyPayload(): array
    {
        return [
            'djs' => [],
            'featured_djs' => [],
            'filters' => [
                'genres' => [],
                'dj_types' => [],
            ],
        ];
    }
}
