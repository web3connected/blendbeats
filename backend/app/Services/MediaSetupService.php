<?php

namespace App\Services;

use App\Models\MediaAccount;
use App\Models\User;
use App\Models\UserFeatureActivation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaSetupService
{
    public const DEFAULT_FOLDERS = ['audio', 'video', 'images', 'documents', 'temp'];

    public function setup(User $user, string $source = 'dj_portfolio'): array
    {
        return DB::transaction(function () use ($user, $source): array {
            $mediaAccount = $user->mediaAccount()->first();

            if (! $mediaAccount) {
                $slug = $this->uniqueAccountSlug($this->baseSlugForUser($user));
                $tier = $user->media_storage_tier ?: config('media_storage.default_tier');
                $tierConfig = config("media_storage.tiers.{$tier}") ?? config('media_storage.tiers.starter');

                $mediaAccount = MediaAccount::create([
                    'user_id' => $user->id,
                    'account_slug' => $slug,
                    'disk' => 'public',
                    'root_path' => "media/accounts/{$slug}",
                    'storage_tier' => $tier,
                    'storage_limit_bytes' => (int) $tierConfig['limit_bytes'],
                    'storage_used_bytes' => 0,
                    'status' => 'active',
                    'activated_at' => now(),
                ]);
            }

            $this->ensureFolders($mediaAccount);
            $this->activateFeature($user, UserFeatureActivation::MEDIA_LIBRARY, $source, [
                'media_account_id' => $mediaAccount->id,
                'root_path' => $mediaAccount->root_path,
            ]);

            return $this->payload($user->refresh());
        });
    }

    public function payload(User $user): array
    {
        $user->load(['mediaAccount', 'featureActivations']);

        return [
            'media_account' => $user->mediaAccount ? $this->mediaAccountPayload($user->mediaAccount) : null,
            'quota' => app(MediaStorageQuotaService::class)->quotaForUser($user),
            'features' => $user->featureActivations
                ->map(fn (UserFeatureActivation $feature): array => $this->featurePayload($feature))
                ->values()
                ->all(),
        ];
    }

    public function activeMediaAccount(User $user): ?MediaAccount
    {
        return $user->mediaAccount()->where('status', 'active')->first();
    }

    private function activateFeature(User $user, string $featureKey, string $source, array $metadata = []): void
    {
        $user->featureActivations()->updateOrCreate(
            ['feature_key' => $featureKey],
            [
                'status' => 'active',
                'source' => $source,
                'metadata' => $metadata,
                'activated_at' => now(),
                'paused_at' => null,
                'disabled_at' => null,
            ],
        );
    }

    private function ensureFolders(MediaAccount $mediaAccount): void
    {
        foreach (self::DEFAULT_FOLDERS as $folder) {
            Storage::disk($mediaAccount->disk)->makeDirectory($mediaAccount->root_path.'/'.$folder);
        }
    }

    private function baseSlugForUser(User $user): string
    {
        $user->loadMissing('djProfile');

        return Str::slug($user->djProfile?->handle ?: $user->name) ?: 'account-'.$user->id;
    }

    private function uniqueAccountSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (MediaAccount::where('account_slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function mediaAccountPayload(MediaAccount $mediaAccount): array
    {
        return [
            'id' => $mediaAccount->id,
            'account_slug' => $mediaAccount->account_slug,
            'disk' => $mediaAccount->disk,
            'root_path' => $mediaAccount->root_path,
            'storage_tier' => $mediaAccount->storage_tier,
            'storage_limit_bytes' => $mediaAccount->storage_limit_bytes,
            'storage_used_bytes' => $mediaAccount->storage_used_bytes,
            'status' => $mediaAccount->status,
            'activated_at' => $mediaAccount->activated_at,
        ];
    }

    private function featurePayload(UserFeatureActivation $feature): array
    {
        return [
            'feature_key' => $feature->feature_key,
            'status' => $feature->status,
            'source' => $feature->source,
            'metadata' => $feature->metadata,
            'activated_at' => $feature->activated_at,
        ];
    }
}
