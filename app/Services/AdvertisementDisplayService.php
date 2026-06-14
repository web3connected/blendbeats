<?php

namespace App\Services;

use App\Models\DjFeaturedStatus;
use Illuminate\Support\Collection;

class AdvertisementDisplayService
{
    private const MAX_DISCOVERY = 1000;

    private const NEW_POOL_LIMIT = 100;

    private const EXISTING_POOL_LIMIT = 400;

    private const NEW_FILTERED_LIMIT = 50;

    private const EXISTING_FILTERED_LIMIT = 200;

    private const WEIGHTED_LIMIT = 100;

    private const FINALIST_LIMIT = 10;

    private const MAX_PLACEMENT_SCORE = 8100;

    private const GROUP_WEIGHTS = [
        1 => 90,
        2 => 80,
        3 => 70,
        4 => 60,
        5 => 50,
        6 => 40,
    ];

    private const SLOT_WEIGHTS = [
        1 => 90,
        2 => 80,
        3 => 70,
        4 => 60,
    ];

    public function __construct(private readonly FeaturedPlacementPricingService $placementPricing) {}

    public function select(?string $placement = null): ?array
    {
        $discovered = $this->discover($placement);

        if ($discovered->isEmpty()) {
            return null;
        }

        $candidatePool = $this->buildCandidatePool($discovered);
        $filteredCandidates = $this->reduceToFilteredCandidates($candidatePool);
        $weightedCandidates = $this->weightedSelect($filteredCandidates, self::WEIGHTED_LIMIT);
        $finalists = $this->buildFinalists($weightedCandidates);
        $winner = $this->selectFinalDisplay($finalists);

        return $winner ? $this->payload($winner) : null;
    }

    /**
     * @return Collection<int, DjFeaturedStatus>
     */
    private function discover(?string $placement = null): Collection
    {
        $ads = DjFeaturedStatus::query()
            ->with([
                'campaignOption:id,name,duration_days',
                'campaignSlot.campaign.slotGroup:id,name,group_key,sort_order',
                'djProfile.user:id,name,email,avatar,is_gravatar,use_gravatar',
            ])
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->latest('claimed_at')
            ->limit(self::MAX_DISCOVERY)
            ->get()
            ->filter(fn (DjFeaturedStatus $campaign): bool => $campaign->djProfile !== null)
            ->values();

        $allowedGroups = $this->allowedGroupNumbersForPlacement($placement);

        if ($allowedGroups === null) {
            return $ads;
        }

        return $ads
            ->filter(fn (DjFeaturedStatus $campaign): bool => in_array($this->groupNumber($campaign), $allowedGroups, true))
            ->values();
    }

    /**
     * @param Collection<int, DjFeaturedStatus> $ads
     * @return Collection<int, DjFeaturedStatus>
     */
    private function buildCandidatePool(Collection $ads): Collection
    {
        $newAds = $ads
            ->filter(fn (DjFeaturedStatus $ad): bool => $this->isFresh($ad))
            ->shuffle()
            ->take(self::NEW_POOL_LIMIT);

        $existingAds = $ads
            ->reject(fn (DjFeaturedStatus $ad): bool => $this->isFresh($ad))
            ->shuffle()
            ->take(self::EXISTING_POOL_LIMIT);

        return $newAds->merge($existingAds)->values();
    }

    /**
     * @param Collection<int, DjFeaturedStatus> $pool
     * @return Collection<int, DjFeaturedStatus>
     */
    private function reduceToFilteredCandidates(Collection $pool): Collection
    {
        $newAds = $pool
            ->filter(fn (DjFeaturedStatus $ad): bool => $this->isFresh($ad))
            ->shuffle()
            ->take(self::NEW_FILTERED_LIMIT);

        $existingAds = $pool
            ->reject(fn (DjFeaturedStatus $ad): bool => $this->isFresh($ad));

        return $newAds
            ->merge($this->weightedSelect($existingAds, self::EXISTING_FILTERED_LIMIT, fn (DjFeaturedStatus $ad): float => $this->loyaltyMultiplier($ad)))
            ->values();
    }

    /**
     * @param Collection<int, DjFeaturedStatus> $ads
     * @param callable|null $weightResolver
     * @return Collection<int, DjFeaturedStatus>
     */
    private function weightedSelect(Collection $ads, int $limit, ?callable $weightResolver = null): Collection
    {
        $remaining = $ads->values();
        $selected = collect();

        while ($selected->count() < $limit && $remaining->isNotEmpty()) {
            $weights = $remaining->map(fn (DjFeaturedStatus $ad): float => max(0.01, (float) ($weightResolver
                ? $weightResolver($ad)
                : $this->selectionWeight($ad))));
            $totalWeight = (float) $weights->sum();
            $target = random_int(1, max(1, (int) round($totalWeight * 1000))) / 1000;
            $cursor = 0.0;
            $selectedIndex = 0;

            foreach ($remaining->values() as $index => $ad) {
                $cursor += (float) $weights[$index];
                if ($cursor >= $target) {
                    $selectedIndex = $index;
                    break;
                }
            }

            $selected->push($remaining[$selectedIndex]);
            $remaining = $remaining->reject(fn ($_ad, int $index): bool => $index === $selectedIndex)->values();
        }

        return $selected->values();
    }

    /**
     * @param Collection<int, DjFeaturedStatus> $ads
     * @return Collection<int, array{ad: DjFeaturedStatus, round_score: float}>
     */
    private function buildFinalists(Collection $ads): Collection
    {
        return $ads
            ->map(function (DjFeaturedStatus $ad): array {
                $placementScore = $this->placementScore($ad);
                $placementMultiplier = $placementScore / self::MAX_PLACEMENT_SCORE;
                $randomQualifier = random_int(1, 10000) * $placementMultiplier;

                return [
                    'ad' => $ad,
                    'round_score' => $placementScore + $randomQualifier,
                ];
            })
            ->sortByDesc('round_score')
            ->take(self::FINALIST_LIMIT)
            ->values();
    }

    /**
     * @param Collection<int, array{ad: DjFeaturedStatus, round_score: float}> $finalists
     */
    private function selectFinalDisplay(Collection $finalists): ?DjFeaturedStatus
    {
        if ($finalists->isEmpty()) {
            return null;
        }

        $target = random_int(1, 10000);

        $selected = $finalists
            ->map(function (array $finalist) use ($target): array {
                return [
                    'ad' => $finalist['ad'],
                    'distance' => abs(random_int(1, 10000) - $target),
                ];
            })
            ->sortBy('distance')
            ->first();

        return is_array($selected) ? $selected['ad'] : null;
    }

    private function selectionWeight(DjFeaturedStatus $ad): float
    {
        $score = $this->placementScore($ad);

        if ($this->isFresh($ad)) {
            $score *= 1.10;
        }

        return $score;
    }

    private function placementScore(DjFeaturedStatus $ad): int
    {
        return $this->groupWeight($ad) * $this->slotWeight($ad);
    }

    private function groupWeight(DjFeaturedStatus $ad): int
    {
        return self::GROUP_WEIGHTS[$this->groupNumber($ad)] ?? 40;
    }

    private function slotWeight(DjFeaturedStatus $ad): int
    {
        return self::SLOT_WEIGHTS[$this->slotPosition($ad)] ?? 60;
    }

    private function allowedGroupNumbersForPlacement(?string $placement): ?array
    {
        return match (strtolower((string) $placement)) {
            'group-a-and-b-display', 'group-a-b-display' => [1, 2],
            'group-e-and-f-display', 'group-e-f-display' => [5, 6],
            default => null,
        };
    }

    private function groupNumber(DjFeaturedStatus $ad): int
    {
        return (int) ($ad->campaignSlot?->campaign?->slotGroup?->sort_order
            ?: $this->placementPricing->groupNumberForSlot((int) $ad->slot_number));
    }

    private function slotPosition(DjFeaturedStatus $ad): int
    {
        return (int) ($ad->campaignSlot?->group_slot_number
            ?: $this->placementPricing->slotPositionForSlot((int) $ad->slot_number));
    }

    private function isFresh(DjFeaturedStatus $ad): bool
    {
        return $ad->created_at !== null && $ad->created_at->gte(now()->subDay());
    }

    private function loyaltyMultiplier(DjFeaturedStatus $ad): float
    {
        $campaignCount = DjFeaturedStatus::query()
            ->where('dj_profile_id', $ad->dj_profile_id)
            ->where('payment_status', 'paid')
            ->count();

        return match (true) {
            $campaignCount >= 10 => 1.15,
            $campaignCount >= 5 => 1.10,
            $campaignCount >= 2 => 1.05,
            default => 1.00,
        };
    }

    private function payload(DjFeaturedStatus $ad): array
    {
        $profile = $ad->djProfile;
        $user = $profile?->user;
        $groupNumber = $this->groupNumber($ad);
        $slotPosition = $this->slotPosition($ad);

        return [
            'id' => $ad->id,
            'type' => 'dj_promotion',
            'title' => $profile?->dj_name,
            'subtitle' => $profile?->profile_headline ?: 'Featured DJ',
            'description' => $profile?->bio,
            'image_url' => $user?->getAvatarUrl(256),
            'url' => $profile?->handle ? url("/djs/{$profile->handle}") : url('/djs'),
            'campaign' => [
                'group' => $this->placementPricing->groupKeyForNumber($groupNumber),
                'group_number' => $groupNumber,
                'slot' => $slotPosition,
                'placement_score' => $this->placementScore($ad),
                'started_at' => $ad->start_date?->toISOString(),
                'ends_at' => $ad->end_date?->toISOString(),
            ],
        ];
    }
}
