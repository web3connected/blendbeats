<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MediaStorageQuotaService
{
    public function __construct(private readonly MembershipTierService $membershipTiers) {}

    public function quotaForOwner(Model $owner): array
    {
        $membershipUser = $owner instanceof User ? $owner : null;
        $tier = $this->membershipTiers->tierFor($membershipUser);
        $tierConfig = $this->membershipTiers->configFor($membershipUser);
        $limitBytes = $this->membershipTiers->storageBytesFor($membershipUser);
        $usedBytes = $this->usedBytes($owner);
        $remainingBytes = max(0, $limitBytes - $usedBytes);

        return [
            'tier' => $tier,
            'tier_label' => $tierConfig['name'],
            'limit_bytes' => $limitBytes,
            'limit_formatted' => MediaManagerService::formatBytes($limitBytes),
            'used_bytes' => $usedBytes,
            'used_formatted' => MediaManagerService::formatBytes($usedBytes),
            'remaining_bytes' => $remainingBytes,
            'remaining_formatted' => MediaManagerService::formatBytes($remainingBytes),
            'usage_percent' => $limitBytes > 0 ? round(($usedBytes / $limitBytes) * 100, 2) : 0,
        ];
    }

    public function assertUploadAllowed(Model $owner, UploadedFile $file): void
    {
        $quota = $this->quotaForOwner($owner);
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

    private function usedBytes(Model $owner): int
    {
        return (int) $owner->mediaFiles()->sum('size');
    }
}
