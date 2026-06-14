<?php

namespace App\Services;

class FeaturedPlacementPricingService
{
    public const GROUP_SIZE = 4;

    public const GROUP_COUNT = 6;

    private const BASE_DAILY_PRICE_CENTS = 2500;

    private const MIN_DAILY_PRICE_CENTS = 599;

    private const GROUP_DECAY = 0.20;

    private const SLOT_DECAY = 0.10;

    private const BASE_EXPOSURE_PERCENT = 100;

    private const MIN_EXPOSURE_PERCENT = 24;

    public function dailyPriceCents(int $groupNumber, int $groupSlotNumber = 1): int
    {
        $groupIndex = max(0, $groupNumber - 1);
        $slotIndex = max(0, $groupSlotNumber - 1);
        $groupMultiplier = (1 - self::GROUP_DECAY) ** $groupIndex;
        $slotMultiplier = (1 - self::SLOT_DECAY) ** $slotIndex;
        $price = (int) round(self::BASE_DAILY_PRICE_CENTS * $groupMultiplier * $slotMultiplier);

        return max(self::MIN_DAILY_PRICE_CENTS, $price);
    }

    public function exposurePercent(int $groupNumber, int $groupSlotNumber = 1): int
    {
        $groupIndex = max(0, $groupNumber - 1);
        $slotIndex = max(0, $groupSlotNumber - 1);
        $groupMultiplier = (1 - self::GROUP_DECAY) ** $groupIndex;
        $slotMultiplier = (1 - self::SLOT_DECAY) ** $slotIndex;
        $exposure = (int) round(self::BASE_EXPOSURE_PERCENT * $groupMultiplier * $slotMultiplier);

        return max(self::MIN_EXPOSURE_PERCENT, $exposure);
    }

    public function rotationWeight(int $groupNumber, int $groupSlotNumber = 1): int
    {
        return $this->exposurePercent($groupNumber, $groupSlotNumber);
    }

    public function templateSlotNumber(int $groupNumber, int $groupSlotNumber): int
    {
        return (($groupNumber - 1) * self::GROUP_SIZE) + $groupSlotNumber;
    }

    public function groupNumberForSlot(int $slotNumber): int
    {
        return intdiv($slotNumber - 1, self::GROUP_SIZE) + 1;
    }

    public function slotPositionForSlot(int $slotNumber): int
    {
        return (($slotNumber - 1) % self::GROUP_SIZE) + 1;
    }

    public function groupKeyForNumber(int $groupNumber): string
    {
        return chr(64 + $groupNumber);
    }

    public function priceRangeLabel(int $groupNumber, int $slotCount = self::GROUP_SIZE): string
    {
        $prices = collect(range(1, $slotCount))
            ->map(fn (int $slotNumber): int => $this->dailyPriceCents($groupNumber, $slotNumber));

        return $this->formatMoney($prices->min()).' - '.$this->formatMoney($prices->max());
    }

    public function formatMoney(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
