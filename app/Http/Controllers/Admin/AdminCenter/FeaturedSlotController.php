<?php

namespace App\Http\Controllers\Admin\AdminCenter;

use App\Http\Controllers\Controller;
use App\Models\FeaturedSlotCampaignOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeaturedSlotController extends Controller
{
    private const SLOT_COUNT = 24;

    private const GROUP_SIZE = 4;

    private const GROUP_WEIGHTS = [35, 25, 15, 10, 8, 7];

    private const MAX_DAILY_PRICE_CENTS = 2500;

    private const MIN_DAILY_PRICE_CENTS = 599;

    public function index(): View
    {
        $slots = collect(range(1, self::SLOT_COUNT))
            ->map(fn (int $slotNumber): array => [
                'number' => $slotNumber,
                'group' => intdiv($slotNumber - 1, self::GROUP_SIZE) + 1,
                'position' => (($slotNumber - 1) % self::GROUP_SIZE) + 1,
                'daily_price_cents' => $this->dailyPriceForGroup(intdiv($slotNumber - 1, self::GROUP_SIZE) + 1),
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
            'groups' => $slots->chunk(self::GROUP_SIZE),
            'campaignOptions' => $campaignOptions,
            'activeCampaignOptions' => $activeCampaignOptions,
            'slotCampaignOptionIds' => $slotCampaignOptionIds,
            'pricingGroups' => collect(range(1, count(self::GROUP_WEIGHTS)))->map(fn (int $group): array => [
                'group' => $group,
                'weight' => self::GROUP_WEIGHTS[$group - 1],
                'daily_price_cents' => $this->dailyPriceForGroup($group),
                'daily_price' => $this->formatMoney($this->dailyPriceForGroup($group)),
            ]),
            'configuredSlotCount' => $slotCampaignOptionIds->filter(fn (array $optionIds) => count($optionIds) > 0)->count(),
            'slotCount' => self::SLOT_COUNT,
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
        abort_unless($slot >= 1 && $slot <= self::SLOT_COUNT, 404);

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

    private function dailyPriceForGroup(int $group): int
    {
        $weight = self::GROUP_WEIGHTS[$group - 1] ?? min(self::GROUP_WEIGHTS);
        $maxWeight = max(self::GROUP_WEIGHTS);
        $minWeight = min(self::GROUP_WEIGHTS);

        if ($maxWeight === $minWeight) {
            return self::MIN_DAILY_PRICE_CENTS;
        }

        $visibilityRatio = ($weight - $minWeight) / ($maxWeight - $minWeight);

        return (int) round(self::MIN_DAILY_PRICE_CENTS + ($visibilityRatio * (self::MAX_DAILY_PRICE_CENTS - self::MIN_DAILY_PRICE_CENTS)));
    }

    private function formatMoney(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
