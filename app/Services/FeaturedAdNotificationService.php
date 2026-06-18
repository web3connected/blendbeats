<?php

namespace App\Services;

use App\Models\DjFeaturedStatus;
use App\Notifications\FeaturedAdNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class FeaturedAdNotificationService
{
    public function notifyPendingPayment(DjFeaturedStatus $campaign): bool
    {
        if ($campaign->pending_payment_notified_at) {
            return false;
        }

        $sent = $this->notify($campaign, [
            'title' => 'Featured ad started',
            'message' => "{$this->campaignTitle($campaign)} is waiting for payment before it can go live.",
            'category' => 'ads',
            'action_label' => 'Finish Payment',
            'action_url' => "/account/featured-ads/placements?campaign={$campaign->id}",
            'icon' => 'megaphone',
            'campaign_id' => $campaign->id,
            'status' => 'pending_payment',
        ]);

        if ($sent) {
            $campaign->forceFill(['pending_payment_notified_at' => now()])->save();
        }

        return $sent;
    }

    public function notifyActivated(DjFeaturedStatus $campaign): bool
    {
        if ($campaign->activated_notified_at) {
            return false;
        }

        $sent = $this->notify($campaign, [
            'title' => 'Featured ad is live',
            'message' => "{$this->campaignTitle($campaign)} is now active in {$this->placementLabel($campaign)}.",
            'category' => 'ads',
            'action_label' => 'View Analytics',
            'action_url' => '/account/featured-ads/analytics',
            'icon' => 'megaphone',
            'campaign_id' => $campaign->id,
            'status' => 'active',
            'ends_at' => $this->dateLabel($campaign->end_date),
        ]);

        if ($sent) {
            $campaign->forceFill(['activated_notified_at' => now()])->save();
        }

        return $sent;
    }

    public function notifyEndingSoon(DjFeaturedStatus $campaign): bool
    {
        if ($campaign->ending_soon_notified_at) {
            return false;
        }

        $sent = $this->notify($campaign, [
            'title' => 'Featured ad ending soon',
            'message' => "{$this->campaignTitle($campaign)} ends {$this->dateLabel($campaign->end_date)}.",
            'category' => 'ads',
            'action_label' => 'Review Ads',
            'action_url' => '/account/featured-ads/analytics',
            'icon' => 'clock',
            'campaign_id' => $campaign->id,
            'status' => 'ending_soon',
            'ends_at' => $this->dateLabel($campaign->end_date),
        ]);

        if ($sent) {
            $campaign->forceFill(['ending_soon_notified_at' => now()])->save();
        }

        return $sent;
    }

    public function notifyExpired(DjFeaturedStatus $campaign): bool
    {
        if ($campaign->expired_notified_at) {
            return false;
        }

        $sent = $this->notify($campaign, [
            'title' => 'Featured ad ended',
            'message' => "{$this->campaignTitle($campaign)} has finished running.",
            'category' => 'ads',
            'action_label' => 'View Results',
            'action_url' => '/account/featured-ads/analytics',
            'icon' => 'check',
            'campaign_id' => $campaign->id,
            'status' => 'expired',
            'ended_at' => $this->dateLabel($campaign->end_date),
        ]);

        if ($sent) {
            $campaign->forceFill(['expired_notified_at' => now()])->save();
        }

        return $sent;
    }

    /**
     * @return array{ending_soon:int, expired:int}
     */
    public function syncEndingNotifications(): array
    {
        $now = now();
        $soon = $now->copy()->addDay();
        $endingSoonCount = 0;
        $expiredCount = 0;

        DjFeaturedStatus::query()
            ->with($this->relations())
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$now, $soon])
            ->whereNull('ending_soon_notified_at')
            ->chunkById(100, function ($campaigns) use (&$endingSoonCount): void {
                foreach ($campaigns as $campaign) {
                    if ($this->notifyEndingSoon($campaign)) {
                        $endingSoonCount++;
                    }
                }
            });

        DjFeaturedStatus::query()
            ->with($this->relations())
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', $now)
            ->chunkById(100, function ($campaigns) use (&$expiredCount): void {
                foreach ($campaigns as $campaign) {
                    $campaign->forceFill(['status' => 'expired'])->save();

                    if ($this->notifyExpired($campaign)) {
                        $expiredCount++;
                    }
                }
            });

        DjFeaturedStatus::query()
            ->with($this->relations())
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->whereNull('end_date')
            ->chunkById(100, function ($campaigns) use ($now, $soon, &$endingSoonCount, &$expiredCount): void {
                foreach ($campaigns as $campaign) {
                    $effectiveEndDate = $campaign->effectiveEndDate();

                    if (! $effectiveEndDate) {
                        continue;
                    }

                    $campaign->forceFill([
                        'end_date' => $effectiveEndDate,
                        'status' => $effectiveEndDate->lte($now) ? 'expired' : $campaign->status,
                    ])->save();

                    if ($effectiveEndDate->lte($now)) {
                        if ($this->notifyExpired($campaign)) {
                            $expiredCount++;
                        }

                        continue;
                    }

                    if ($effectiveEndDate->lte($soon) && $this->notifyEndingSoon($campaign)) {
                        $endingSoonCount++;
                    }
                }
            });

        return [
            'ending_soon' => $endingSoonCount,
            'expired' => $expiredCount,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function notify(DjFeaturedStatus $campaign, array $payload): bool
    {
        if (! Schema::hasTable('notifications')) {
            return false;
        }

        $campaign->loadMissing($this->relations());
        $user = $campaign->djProfile?->user;

        if (! $user) {
            return false;
        }

        $user->notify(new FeaturedAdNotification($payload));

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'campaignOption:id,name,duration_days',
            'campaignSlot.campaign.slotGroup:id,name,group_key,sort_order',
            'djProfile.user:id,name,email',
        ];
    }

    private function campaignTitle(DjFeaturedStatus $campaign): string
    {
        return $campaign->campaignSlot?->campaign?->title ?: 'Featured ad campaign';
    }

    private function placementLabel(DjFeaturedStatus $campaign): string
    {
        $group = $campaign->campaignSlot?->campaign?->slotGroup?->group_key;
        $slot = $campaign->campaignSlot?->group_slot_number;

        return $group && $slot ? "Group {$group} / Slot {$slot}" : "Slot {$campaign->slot_number}";
    }

    private function dateLabel(null|string|Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        return $date instanceof Carbon
            ? $date->format('M j, Y g:i A')
            : Carbon::parse($date)->format('M j, Y g:i A');
    }
}
