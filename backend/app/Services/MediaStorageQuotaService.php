<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MediaStorageQuotaService
{
    public function quotaForUser(User $user): array
    {
        $tier = $user->media_storage_tier ?: config('media_storage.default_tier');
        $tierConfig = config("media_storage.tiers.{$tier}") ?? config('media_storage.tiers.starter');
        $limitBytes = (int) $tierConfig['limit_bytes'];
        $usedBytes = $this->usedBytes($user);
        $remainingBytes = max(0, $limitBytes - $usedBytes);

        return [
            'tier' => $tier,
            'tier_label' => $tierConfig['label'],
            'limit_bytes' => $limitBytes,
            'limit_formatted' => MediaManagerService::formatBytes($limitBytes),
            'used_bytes' => $usedBytes,
            'used_formatted' => MediaManagerService::formatBytes($usedBytes),
            'remaining_bytes' => $remainingBytes,
            'remaining_formatted' => MediaManagerService::formatBytes($remainingBytes),
            'usage_percent' => $limitBytes > 0 ? round(($usedBytes / $limitBytes) * 100, 2) : 0,
        ];
    }

    public function assertUploadAllowed(User $user, UploadedFile $file): void
    {
        $quota = $this->quotaForUser($user);
        $uploadSize = $file->getSize() ?: 0;

        if (($quota['used_bytes'] + $uploadSize) <= $quota['limit_bytes']) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => [
                "Upload exceeds your {$quota['tier_label']} storage limit of {$quota['limit_formatted']}. ".
                "You have {$quota['remaining_formatted']} remaining.",
            ],
        ]);
    }

    private function usedBytes(User $user): int
    {
        return (int) MediaFile::where('user_id', $user->id)->sum('size');
    }
}
