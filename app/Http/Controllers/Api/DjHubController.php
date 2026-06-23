<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserGamificationStat;
use App\Services\FeaturedAdNotificationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DjHubController extends Controller
{
    public function __construct(private readonly FeaturedAdNotificationService $adNotifications) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:80'],
            'dj_type' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:255'],
            'bookings' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:featured,new,followers,top,name'],
        ]);

        if (! $this->hasDjHubTables()) {
            return response()->json($this->emptyPayload());
        }

        $this->syncFeaturedAdWindows();

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
            'top' => $profiles->orderByDesc('engagement_score')->orderByDesc('followers_count')->orderByDesc('dj_profiles.view_count'),
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

        $this->syncFeaturedAdWindows();

        $profile = $this->baseProfileQuery()
            ->where('dj_profiles.handle', $handle)
            ->first();

        abort_unless($profile, 404);

        return response()->json([
            'dj' => $this->profilePayload($profile, true),
        ]);
    }

    private function hasDjHubTables(): bool
    {
        return Schema::hasTable('dj_profiles')
            && Schema::hasTable('dj_genres')
            && Schema::hasTable('dj_profile_genres');
    }

    private function syncFeaturedAdWindows(): void
    {
        if (
            Schema::hasTable('dj_featured_status')
            && Schema::hasTable('featured_slot_campaign_options')
            && Schema::hasTable('featured_campaign_slots')
            && Schema::hasTable('featured_campaigns')
            && Schema::hasTable('featured_slot_groups')
        ) {
            $this->adNotifications->syncEndingNotifications();
        }
    }

    private function baseProfileQuery(): Builder
    {
        $followersSubquery = Schema::hasTable('followers')
            ? DB::table('followers')
                ->selectRaw('count(*)')
                ->whereColumn('followers.followed_dj_id', 'dj_profiles.id')
            : DB::query()->selectRaw('0');

        $featuredSubquery = Schema::hasTable('dj_featured_status')
            ? DB::table('dj_featured_status')
                ->selectRaw('count(*)')
                ->whereColumn('dj_featured_status.dj_profile_id', 'dj_profiles.id')
                ->where('dj_featured_status.status', 'active')
                ->where(fn (Builder $query) => $query->whereNull('dj_featured_status.start_date')->orWhere('dj_featured_status.start_date', '<=', now()))
                ->where(fn (Builder $query) => $query->whereNull('dj_featured_status.end_date')->orWhere('dj_featured_status.end_date', '>=', now()))
            : DB::query()->selectRaw('0');

        $engagementScoreSubquery = Schema::hasTable('followers')
            ? DB::table('followers')
                ->selectRaw('(count(*) * 2) + coalesce(dj_profiles.view_count, 0)')
                ->whereColumn('followers.followed_dj_id', 'dj_profiles.id')
            : DB::query()->selectRaw('coalesce(dj_profiles.view_count, 0)');

        return DB::table('dj_profiles')
            ->join('users', 'users.id', '=', 'dj_profiles.user_id')
            ->select([
                'dj_profiles.*',
                'users.name as user_name',
                'users.email as user_email',
                'users.avatar as user_avatar',
                'users.use_gravatar as user_use_gravatar',
                'users.is_gravatar as user_is_gravatar',
            ])
            ->selectSub($followersSubquery, 'followers_count')
            ->selectSub($featuredSubquery, 'featured_count')
            ->selectSub($engagementScoreSubquery, 'engagement_score')
            ->where('dj_profiles.visibility', 'public')
            ->where('dj_profiles.profile_status', 'active');
    }

    private function profilePayload(object $profile, bool $includeDetails = false): array
    {
        $genres = $this->genresFor((int) $profile->id);
        $primaryGenre = collect($genres)->firstWhere('is_primary', true)['name'] ?? null;
        $secondaryGenres = collect($genres)
            ->reject(fn (array $genre): bool => (bool) $genre['is_primary'])
            ->pluck('name')
            ->values()
            ->all();

        $payload = [
            'id' => (int) $profile->id,
            'dj_name' => $profile->dj_name,
            'handle' => $profile->handle,
            'headline' => $profile->profile_headline ?? null,
            'bio' => $profile->bio ?? null,
            'avatar_url' => $this->avatarUrl($profile),
            'primary_genre' => $primaryGenre,
            'secondary_genres' => $secondaryGenres,
            'dj_type' => $profile->dj_type ?? null,
            'location' => collect([$profile->city ?? null, $profile->state ?? null, $profile->country ?? null])->filter()->join(', '),
            'city' => $profile->city ?? null,
            'state' => $profile->state ?? null,
            'country' => $profile->country ?? null,
            'open_for_bookings' => (bool) ($profile->booking_enabled ?? false),
            'followers_count' => (int) ($profile->followers_count ?? 0),
            'is_following' => $this->isFollowing((int) $profile->id),
            'engagement_score' => (int) ($profile->engagement_score ?? 0),
            'view_count' => (int) ($profile->view_count ?? 0),
            'featured_slot' => $this->featuredSlotFor((int) $profile->id),
            'featured_statuses' => $this->featuredStatusesFor((int) $profile->id),
            'featured_mix' => $this->featuredMixFor((int) $profile->user_id),
            'gamification' => $this->djGamificationFor((int) $profile->user_id),
        ];

        if ($includeDetails) {
            $allPortfolioMedia = $this->portfolioMediaFor((int) $profile->user_id);
            $portfolioMedia = array_slice($allPortfolioMedia, 0, 8);

            $payload['portfolio_media'] = $portfolioMedia;
            $payload['portfolio_stats'] = $this->portfolioStatsFor($allPortfolioMedia);
        }

        return $payload;
    }

    private function djGamificationFor(int $userId): array
    {
        $badges = $this->publicDjBadgesFor($userId);

        if (! Schema::hasTable('user_gamification_stats')) {
            return [
                'dj_xp' => 0,
                'dj_level' => 1,
                'dj_rank' => 'New DJ',
                'badges' => $badges,
            ];
        }

        $stats = UserGamificationStat::query()
            ->where('user_id', $userId)
            ->first();

        return [
            'dj_xp' => (int) ($stats?->dj_xp ?? 0),
            'dj_level' => (int) ($stats?->dj_level ?? 1),
            'dj_rank' => $stats?->dj_rank ?: 'New DJ',
            'badges' => $badges,
        ];
    }

    private function featuredSlotFor(int $profileId): ?int
    {
        if (! Schema::hasTable('dj_featured_status') || ! Schema::hasColumn('dj_featured_status', 'slot_number')) {
            return null;
        }

        $slot = DB::table('dj_featured_status')
            ->where('dj_profile_id', $profileId)
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('start_date')->orWhere('start_date', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->orderBy('slot_number')
            ->value('slot_number');

        return $slot ? (int) $slot : null;
    }

    private function isFollowing(int $profileId): bool
    {
        $userId = auth('web')->id();

        if (! $userId || ! Schema::hasTable('followers')) {
            return false;
        }

        return DB::table('followers')
            ->where('follower_user_id', $userId)
            ->where('followed_dj_id', $profileId)
            ->exists();
    }

    private function publicDjBadgesFor(int $userId): array
    {
        if (! Schema::hasTable('user_badges') || ! Schema::hasTable('badges')) {
            return [];
        }

        return UserBadge::query()
            ->with('badge:id,badge_key,name,icon,rarity,role_context')
            ->where('user_id', $userId)
            ->latest('unlocked_at')
            ->latest('id')
            ->get()
            ->filter(fn (UserBadge $userBadge): bool => in_array($userBadge->badge?->role_context, ['dj', 'both'], true))
            ->map(fn (UserBadge $userBadge): array => [
                'badge_key' => $userBadge->badge?->badge_key,
                'name' => $userBadge->badge?->name,
                'icon' => $userBadge->badge?->icon,
                'rarity' => $userBadge->badge?->rarity,
            ])
            ->values()
            ->all();
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

        $mix = MediaFile::query()
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
            'title' => $mix->metadata['portfolio']['title'] ?? $mix->original_name ?? $mix->name,
            'url' => $mix->url,
            'mime_type' => $mix->mime_type,
        ];
    }

    private function portfolioMediaFor(int $userId): array
    {
        if (! Schema::hasTable('media_files')) {
            return [];
        }

        return MediaFile::query()
            ->where('user_id', $userId)
            ->where('collection', 'dj_media')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->filter(fn (MediaFile $file): bool => ($file->metadata['portfolio']['visibility'] ?? null) === 'public')
            ->map(fn (MediaFile $file): array => $this->portfolioMediaPayload($file))
            ->values()
            ->all();
    }

    private function portfolioStatsFor(array $portfolioMedia): array
    {
        $media = collect($portfolioMedia);

        return [
            'public_media_count' => $media->count(),
            'audio_count' => $media->where('is_audio', true)->count(),
            'video_count' => $media->where('is_video', true)->count(),
            'genre_count' => $media->pluck('genre')->filter()->unique()->count(),
        ];
    }

    private function portfolioMediaPayload(MediaFile $file): array
    {
        $portfolio = $file->metadata['portfolio'] ?? [];

        return [
            'id' => (int) $file->id,
            'title' => $portfolio['title'] ?? $file->original_name ?? $file->name,
            'description' => $portfolio['description'] ?? null,
            'genre' => $portfolio['genre'] ?? null,
            'kind' => $portfolio['media_kind'] ?? null,
            'url' => $file->url,
            'external_provider' => $portfolio['external_provider'] ?? $file->metadata['external_source']['provider'] ?? null,
            'cover_image_url' => $portfolio['cover_image_url'] ?? null,
            'mime_type' => $file->mime_type,
            'formatted_size' => $file->formatted_size,
            'is_audio' => $file->isAudio(),
            'is_video' => $file->isVideo(),
            'is_image' => $file->isImage(),
            'created_at' => optional($file->created_at)->toISOString(),
        ];
    }

    private function featuredDjs(): array
    {
        if (! Schema::hasTable('dj_featured_status')) {
            return [];
        }

        $featuredRows = DB::table('dj_featured_status')
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('start_date')->orWhere('start_date', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->when(
                Schema::hasColumn('dj_featured_status', 'slot_number'),
                fn (Builder $query) => $query->orderBy('slot_number'),
                fn (Builder $query) => $query->orderByDesc('start_date'),
            )
            ->orderByDesc('id')
            ->get(['dj_profile_id'])
            ->unique('dj_profile_id')
            ->take(24)
            ->values();

        if ($featuredRows->isEmpty()) {
            return [];
        }

        $profileIds = $featuredRows->pluck('dj_profile_id')->all();

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

    private function avatarUrl(object $profile): string
    {
        $user = new User();
        $user->setRawAttributes([
            'id' => (int) $profile->user_id,
            'name' => $profile->user_name ?? $profile->dj_name,
            'email' => $profile->user_email ?? null,
            'avatar' => $profile->user_avatar ?? null,
            'use_gravatar' => (bool) ($profile->user_use_gravatar ?? false),
            'is_gravatar' => (bool) ($profile->user_is_gravatar ?? false),
        ], true);

        return $user->getAvatarUrl();
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
