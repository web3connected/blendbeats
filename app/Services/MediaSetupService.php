<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\MediaAccount;
use App\Models\User;
use App\Models\UserFeatureActivation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaSetupService
{
    public const DEFAULT_FOLDERS = ['audio', 'video', 'images', 'documents', 'temp'];

    public function setup(Model $owner, string $source = 'media_library'): array
    {
        return DB::transaction(function () use ($owner, $source): array {
            $mediaAccount = $owner->mediaAccount()->first();

            if (! $mediaAccount) {
                $slug = $this->uniqueAccountSlug($this->baseSlugForOwner($owner));
                $tier = $owner->media_storage_tier ?? config('media_storage.default_tier');
                $tier = config("media_storage.tier_aliases.{$tier}", $tier);
                $tierConfig = config("billing.subscription.tiers.{$tier}")
                    ?? config('billing.subscription.tiers.'.config('billing.subscription.free_tier', 'free'));

                $mediaAccount = MediaAccount::create([
                    ...$this->ownerColumns($owner),
                    'account_slug' => $slug,
                    'disk' => 'public',
                    'root_path' => "media/accounts/{$slug}",
                    'storage_tier' => $tier,
                    'storage_limit_bytes' => (int) $tierConfig['storage_bytes'],
                    'storage_used_bytes' => 0,
                    'status' => 'active',
                    'activated_at' => now(),
                ]);
            }

            $this->ensureFolders($mediaAccount);
            $this->activateFeature($owner, UserFeatureActivation::MEDIA_LIBRARY, $source, [
                'media_account_id' => $mediaAccount->id,
                'root_path' => $mediaAccount->root_path,
            ]);

            return $this->payload($owner->refresh());
        });
    }

    public function payload(Model $owner): array
    {
        $owner->load(['mediaAccount', 'featureActivations']);

        return [
            'media_account' => $owner->mediaAccount ? $this->mediaAccountPayload($owner->mediaAccount) : null,
            'quota' => app(MediaStorageQuotaService::class)->quotaForOwner($owner),
            'features' => $owner->featureActivations
                ->map(fn (UserFeatureActivation $feature): array => $this->featurePayload($feature))
                ->values()
                ->all(),
        ];
    }

    public function activeMediaAccount(Model $owner): ?MediaAccount
    {
        return $owner->mediaAccount()->where('status', 'active')->first();
    }

    private function activateFeature(Model $owner, string $featureKey, string $source, array $metadata = []): void
    {
        $owner->featureActivations()->updateOrCreate(
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

    private function baseSlugForOwner(Model $owner): string
    {
        $prefix = $owner instanceof Admin ? 'admin' : 'user';

        return Str::slug($prefix.'-'.$owner->name) ?: "{$prefix}-{$owner->getKey()}";
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

    private function ownerColumns(Model $owner): array
    {
        if ($owner instanceof Admin) {
            return ['admin_id' => $owner->getKey()];
        }

        if ($owner instanceof User) {
            return ['user_id' => $owner->getKey()];
        }

        throw new \InvalidArgumentException('Media library owner must be a User or Admin model.');
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
