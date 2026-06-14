<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DjGenre;
use App\Models\DjMedia;
use App\Models\DjProfile;
use App\Models\DjSocialLink;
use App\Notifications\DjProfileCreatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DjProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = Auth::user()
            ->djProfile()
            ->with(['genres', 'socialLinks', 'bookingSetting', 'media'])
            ->firstOrFail();

        return response()->json([
            'dj_profile' => $this->profilePayload($profile),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $existingProfile = $user->djProfile;
        $isFinalSave = ! $request->has('is_final') || $request->boolean('is_final');

        $attributes = $request->validate([
            'is_final' => ['sometimes', 'boolean'],
            'setup_step' => ['sometimes', 'integer', 'min:0', 'max:3'],
            'dj_name' => ['required', 'string', 'max:255'],
            'handle' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('dj_profiles', 'handle')->ignore($existingProfile?->id),
            ],
            'profile_headline' => ['nullable', 'string', 'max:255'],
            'bio' => [$isFinalSave ? 'required' : 'nullable', 'string', 'max:5000'],
            'banner_url' => ['nullable', 'url', 'max:255'],
            'primary_genre' => [$isFinalSave ? 'required' : 'nullable', 'string', 'max:80'],
            'secondary_genres' => ['nullable', 'array', 'max:12'],
            'secondary_genres.*' => ['string', 'max:80'],
            'dj_type' => [
                'nullable',
                Rule::in([
                    'battle_dj',
                    'club_dj',
                    'radio_dj',
                    'mobile_event_dj',
                    'producer_dj',
                    'turntablist',
                    'open_format',
                ]),
            ],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'tiktok' => ['nullable', 'url', 'max:255'],
            'youtube' => ['nullable', 'url', 'max:255'],
            'soundcloud' => ['nullable', 'url', 'max:255'],
            'mixcloud' => ['nullable', 'url', 'max:255'],
            'twitch' => ['nullable', 'url', 'max:255'],
            'spotify' => ['nullable', 'url', 'max:255'],
            'available_for_bookings' => ['boolean'],
            'booking_email' => ['nullable', 'email', 'max:255'],
            'visibility' => [$isFinalSave ? 'required' : 'nullable', Rule::in(['public', 'followers', 'private'])],
        ]);

        $profile = DB::transaction(function () use ($attributes, $user, $existingProfile, $isFinalSave): DjProfile {
            $isActiveProfile = $existingProfile?->profile_status === 'active';

            $profile = DjProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'dj_name' => $attributes['dj_name'],
                    'handle' => Str::lower($attributes['handle']),
                    'profile_headline' => $attributes['profile_headline'] ?? null,
                    'bio' => $attributes['bio'] ?? null,
                    'dj_type' => $attributes['dj_type'] ?? null,
                    'city' => $attributes['city'] ?? null,
                    'state' => $attributes['state'] ?? null,
                    'country' => $attributes['country'] ?? null,
                    'booking_enabled' => (bool) ($attributes['available_for_bookings'] ?? false),
                    'profile_status' => ($isFinalSave || $isActiveProfile) ? 'active' : 'draft',
                    'visibility' => $attributes['visibility'] ?? 'private',
                    'published_at' => ($isFinalSave || $isActiveProfile) ? ($existingProfile?->published_at ?? now()) : null,
                ],
            );

            if (! empty($attributes['primary_genre'])) {
                $this->syncGenres($profile, $attributes['primary_genre'], $attributes['secondary_genres'] ?? []);
            }

            $this->syncSocialLinks($profile, $attributes);
            $this->syncBookingSettings($profile, $attributes);
            $this->syncBanner($profile, $attributes['banner_url'] ?? null);

            return $profile->load(['genres', 'socialLinks', 'bookingSetting', 'media']);
        });

        if (! $existingProfile && $isFinalSave && Schema::hasTable('notifications')) {
            $user->notify(new DjProfileCreatedNotification($profile));
        }

        return response()->json([
            'dj_profile' => $this->profilePayload($profile),
        ]);
    }

    private function syncGenres(DjProfile $profile, string $primaryGenre, array $secondaryGenres): void
    {
        $genreNames = collect([$primaryGenre, ...$secondaryGenres])
            ->map(fn (string $genre): string => trim($genre))
            ->filter()
            ->unique()
            ->values();

        $syncPayload = [];

        foreach ($genreNames as $index => $genreName) {
            $genre = DjGenre::firstOrCreate(
                ['slug' => Str::slug($genreName)],
                ['name' => $genreName, 'is_active' => true],
            );

            $syncPayload[$genre->id] = [
                'is_primary' => $genreName === $primaryGenre,
                'sort_order' => $index,
            ];
        }

        $profile->genres()->sync($syncPayload);
    }

    private function syncSocialLinks(DjProfile $profile, array $attributes): void
    {
        $platforms = ['website', 'instagram', 'tiktok', 'youtube', 'soundcloud', 'mixcloud', 'twitch', 'spotify'];

        foreach ($platforms as $index => $platform) {
            $url = $attributes[$platform] ?? null;

            if (! $url) {
                DjSocialLink::where('dj_profile_id', $profile->id)->where('platform', $platform)->delete();
                continue;
            }

            DjSocialLink::updateOrCreate(
                ['dj_profile_id' => $profile->id, 'platform' => $platform],
                ['url' => $url, 'sort_order' => $index],
            );
        }
    }

    private function syncBookingSettings(DjProfile $profile, array $attributes): void
    {
        $profile->bookingSetting()->updateOrCreate(
            ['dj_profile_id' => $profile->id],
            [
                'available_for_bookings' => (bool) ($attributes['available_for_bookings'] ?? false),
                'booking_email' => $attributes['booking_email'] ?? null,
                'show_booking_email' => (bool) ($attributes['available_for_bookings'] ?? false),
            ],
        );
    }

    private function syncBanner(DjProfile $profile, ?string $bannerUrl): void
    {
        if (! $bannerUrl) {
            DjMedia::where('dj_profile_id', $profile->id)->where('type', 'banner')->delete();
            return;
        }

        DjMedia::updateOrCreate(
            ['dj_profile_id' => $profile->id, 'type' => 'banner', 'is_primary' => true],
            ['url' => $bannerUrl, 'sort_order' => 0],
        );
    }

    private function profilePayload(DjProfile $profile): array
    {
        $primaryGenre = $profile->genres->firstWhere('pivot.is_primary', true);
        $secondaryGenres = $profile->genres
            ->filter(fn (DjGenre $genre): bool => ! (bool) $genre->pivot->is_primary)
            ->pluck('name')
            ->values();
        $socialLinks = $profile->socialLinks->pluck('url', 'platform');
        $banner = $profile->media
            ->where('type', 'banner')
            ->where('is_primary', true)
            ->first()
            ?? $profile->media->firstWhere('type', 'banner');

        return [
            'id' => $profile->id,
            'dj_name' => $profile->dj_name,
            'handle' => $profile->handle,
            'profile_headline' => $profile->profile_headline,
            'bio' => $profile->bio,
            'banner_url' => $banner?->url,
            'primary_genre' => $primaryGenre?->name,
            'secondary_genres' => $secondaryGenres,
            'dj_type' => $profile->dj_type,
            'city' => $profile->city,
            'state' => $profile->state,
            'country' => $profile->country,
            'website' => $socialLinks->get('website'),
            'instagram' => $socialLinks->get('instagram'),
            'tiktok' => $socialLinks->get('tiktok'),
            'youtube' => $socialLinks->get('youtube'),
            'soundcloud' => $socialLinks->get('soundcloud'),
            'mixcloud' => $socialLinks->get('mixcloud'),
            'twitch' => $socialLinks->get('twitch'),
            'spotify' => $socialLinks->get('spotify'),
            'available_for_bookings' => (bool) $profile->bookingSetting?->available_for_bookings,
            'booking_email' => $profile->bookingSetting?->booking_email,
            'profile_status' => $profile->profile_status,
            'visibility' => $profile->visibility,
        ];
    }
}
