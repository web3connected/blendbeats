<?php

namespace App\Http\Controllers\Admin\AdminCenter;

use App\Http\Controllers\Controller;
use App\Models\FeaturedSlotCampaignOption;
use App\Services\FeaturedPlacementPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeaturedSlotController extends Controller
{
    public function __construct(private readonly FeaturedPlacementPricingService $placementPricing) {}

    public function index(): View
    {
        $slotCount = FeaturedPlacementPricingService::GROUP_COUNT * FeaturedPlacementPricingService::GROUP_SIZE;
        $slots = collect(range(1, $slotCount))
            ->map(fn (int $slotNumber): array => [
                'number' => $slotNumber,
                'group' => $this->placementPricing->groupNumberForSlot($slotNumber),
                'position' => $this->placementPricing->slotPositionForSlot($slotNumber),
                'daily_price_cents' => $this->placementPricing->dailyPriceCents(
                    $this->placementPricing->groupNumberForSlot($slotNumber),
                    $this->placementPricing->slotPositionForSlot($slotNumber),
                ),
                'daily_price' => $this->placementPricing->formatMoney($this->placementPricing->dailyPriceCents(
                    $this->placementPricing->groupNumberForSlot($slotNumber),
                    $this->placementPricing->slotPositionForSlot($slotNumber),
                )),
                'exposure_percent' => $this->placementPricing->exposurePercent(
                    $this->placementPricing->groupNumberForSlot($slotNumber),
                    $this->placementPricing->slotPositionForSlot($slotNumber),
                ),
                'rotation_weight' => $this->placementPricing->rotationWeight(
                    $this->placementPricing->groupNumberForSlot($slotNumber),
                    $this->placementPricing->slotPositionForSlot($slotNumber),
                ),
            ]);

        $campaignOptions = Schema::hasTable('featured_slot_campaign_options')
            ? FeaturedSlotCampaignOption::query()
                ->orderBy('sort_order')
                ->orderBy('duration_days')
                ->get()
            : collect();
        $activeCampaignOptions = $campaignOptions->where('is_active', true)->values();
        $slotCampaignOptionIds = Schema::hasTable('featured_slot_campaign_option_slot')
            ? DB::table('featured_slot_campaign_option_slot')
                ->select(['slot_number', 'featured_slot_campaign_option_id'])
                ->get()
                ->groupBy('slot_number')
                ->map(fn ($rows) => $rows->pluck('featured_slot_campaign_option_id')->map(fn ($id) => (int) $id)->all())
            : collect();

        return view('admin.featured-slots.index', [
            'slots' => $slots,
            'groups' => $slots->chunk(FeaturedPlacementPricingService::GROUP_SIZE),
            'campaignOptions' => $campaignOptions,
            'activeCampaignOptions' => $activeCampaignOptions,
            'slotCampaignOptionIds' => $slotCampaignOptionIds,
            'pricingGroups' => collect(range(1, FeaturedPlacementPricingService::GROUP_COUNT))->map(fn (int $group): array => [
                'group' => $group,
                'weight' => $this->placementPricing->rotationWeight($group),
                'daily_price_cents' => $this->placementPricing->dailyPriceCents($group),
                'daily_price' => $this->placementPricing->formatMoney($this->placementPricing->dailyPriceCents($group)),
                'daily_price_range' => $this->placementPricing->priceRangeLabel($group),
                'min_exposure_percent' => $this->placementPricing->exposurePercent($group, FeaturedPlacementPricingService::GROUP_SIZE),
                'max_exposure_percent' => $this->placementPricing->exposurePercent($group),
            ]),
            'configuredSlotCount' => $slotCampaignOptionIds->filter(fn (array $optionIds) => count($optionIds) > 0)->count(),
            'slotCount' => $slotCount,
        ]);
    }

    public function storeCampaignOption(Request $request): RedirectResponse
    {
        FeaturedSlotCampaignOption::query()->create($this->validateCampaignOption($request));

        return redirect()
            ->route('admin.admincenter.featuredslots.index')
            ->with('status', 'Campaign option created.');
    }

    public function updateCampaignOption(Request $request, FeaturedSlotCampaignOption $option): RedirectResponse
    {
        $option->update($this->validateCampaignOption($request));

        return redirect()
            ->route('admin.admincenter.featuredslots.index')
            ->with('status', 'Campaign option updated.');
    }

    public function destroyCampaignOption(FeaturedSlotCampaignOption $option): RedirectResponse
    {
        $option->delete();

        return redirect()
            ->route('admin.admincenter.featuredslots.index')
            ->with('status', 'Campaign option deleted.');
    }

    public function update(Request $request, int $slot): RedirectResponse
    {
        $slotCount = FeaturedPlacementPricingService::GROUP_COUNT * FeaturedPlacementPricingService::GROUP_SIZE;
        abort_unless($slot >= 1 && $slot <= $slotCount, 404);

        if ($request->boolean('clear_slot')) {
            if (Schema::hasTable('featured_slot_campaign_option_slot')) {
                DB::table('featured_slot_campaign_option_slot')->where('slot_number', $slot)->delete();
            }

            return redirect()
                ->route('admin.admincenter.featuredslots.index')
                ->with('status', "Featured slot {$slot} claim options cleared.");
        }

        $validated = $request->validate([
            'campaign_option_ids' => ['array'],
            'campaign_option_ids.*' => [
                'integer',
                Rule::exists('featured_slot_campaign_options', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        $campaignOptionIds = collect($validated['campaign_option_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        if (Schema::hasTable('featured_slot_campaign_option_slot')) {
            DB::table('featured_slot_campaign_option_slot')->where('slot_number', $slot)->delete();

            $now = now();
            $campaignOptionIds->each(function (int $optionId) use ($slot, $now): void {
                DB::table('featured_slot_campaign_option_slot')->insert([
                    'slot_number' => $slot,
                    'featured_slot_campaign_option_id' => $optionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        }

        return redirect()
            ->route('admin.admincenter.featuredslots.index')
            ->with('status', "Featured slot {$slot} claim options updated.");
    }

    private function validateCampaignOption(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_days' => $validated['duration_days'],
            'price_cents' => null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

}
