<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeaturedCampaign;
use App\Models\FeaturedCampaignSlot;
use App\Models\DjFeaturedStatus;
use App\Models\FeaturedSlotCampaignOption;
use App\Models\PaymentProvider;
use App\Services\MembershipTierService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class FeaturedAdController extends Controller
{
    private const SLOT_COUNT = 24;

    private const GROUP_SIZE = 4;

    private const GROUP_WEIGHTS = [35, 25, 15, 10, 8, 7];

    private const MAX_DAILY_PRICE_CENTS = 2500;

    private const MIN_DAILY_PRICE_CENTS = 599;

    public function __construct(private readonly MembershipTierService $membershipTiers) {}

    public function placements(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->djProfile;
        $availableGroups = $this->membershipTiers->advertisingGroupsFor($user);

        $campaigns = FeaturedCampaign::query()
            ->with([
                'slotGroup:id,name,group_key,slot_count,template_type,rotation_weight,daily_price_cents,sort_order,is_active',
                'slots.featuredStatus.djProfile:id,dj_name,handle,user_id',
                'slots.featuredStatus.campaignOption:id,name,duration_days',
            ])
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', now()))
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->orderBy('sort_order')
            ->latest()
            ->get()
            ->filter(fn (FeaturedCampaign $campaign): bool => (bool) $campaign->slotGroup?->is_active)
            ->values();

        return response()->json([
            'membership' => [
                'tier' => $this->membershipTiers->tierFor($user),
                'groups' => $availableGroups,
            ],
            'campaigns' => $campaigns
                ->map(fn (FeaturedCampaign $campaign): array => $this->marketplaceCampaignPayload($campaign, $availableGroups))
                ->values(),
            'my_campaigns' => $profile
                ? DjFeaturedStatus::query()
                    ->with(['campaignOption:id,name,duration_days', 'campaignSlot.campaign:id,title'])
                    ->where('dj_profile_id', $profile->id)
                    ->latest()
                    ->take(12)
                    ->get()
                    ->map(fn (DjFeaturedStatus $campaign): array => $this->campaignPayload($campaign))
                    ->values()
                : [],
            'payment_provider' => $this->primaryPaymentProviderPayload(),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_slot_id' => ['required', 'integer', Rule::exists('featured_campaign_slots', 'id')],
            'campaign_option_id' => [
                'required',
                'integer',
                Rule::exists('featured_slot_campaign_options', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        $user = $request->user();
        $profile = $user->djProfile;
        abort_unless($profile && $profile->profile_status === 'active' && $profile->visibility === 'public', 422, 'Create an active public DJ profile before claiming featured placements.');

        $campaignSlot = FeaturedCampaignSlot::query()
            ->with(['campaign.slotGroup'])
            ->findOrFail($validated['campaign_slot_id']);
        $campaign = $campaignSlot->campaign;
        $slotGroup = $campaign?->slotGroup;
        abort_unless($campaign && $slotGroup && $campaign->status === 'active' && $slotGroup->is_active, 422, 'This featured campaign is not available.');
        abort_unless((! $campaign->start_date || $campaign->start_date <= now()) && (! $campaign->end_date || $campaign->end_date >= now()), 422, 'This featured campaign is not currently active.');

        $groupNumber = (int) $slotGroup->sort_order;
        $groupKey = (string) $slotGroup->group_key;
        abort_unless($this->membershipTiers->canAccessAdvertisingGroup($user, $groupKey), 403, "Your membership does not include Group {$groupKey} advertising.");
        abort_unless($this->campaignSlotHasOption($campaignSlot, (int) $validated['campaign_option_id']), 422, 'That campaign option is not available for this slot.');
        abort_if($this->campaignSlotHasActiveOrPendingCampaign($campaignSlot), 422, 'That campaign slot is already claimed or pending payment.');

        $option = FeaturedSlotCampaignOption::query()->findOrFail($validated['campaign_option_id']);
        $amountCents = (int) $slotGroup->daily_price_cents * $option->duration_days;
        $provider = $this->primaryPaymentProvider();

        abort_unless($provider, 422, 'No active payment provider is configured.');
        abort_unless($provider->provider === 'paypal', 422, "{$provider->display_name} promotion checkout is not connected yet.");
        abort_unless($provider->hasEffectiveValueFor('client_id') && $provider->hasEffectiveSecret(), 422, 'PayPal credentials are not ready.');

        $campaign = DjFeaturedStatus::query()->create([
            'dj_profile_id' => $profile->id,
            'slot_number' => $this->templateSlotNumber($groupNumber, (int) $campaignSlot->group_slot_number),
            'featured_slot_campaign_option_id' => $option->id,
            'featured_campaign_slot_id' => $campaignSlot->id,
            'featured_type' => 'paid_placement',
            'rotation_weight' => (int) $slotGroup->rotation_weight,
            'amount_cents' => $amountCents,
            'currency' => 'USD',
            'payment_provider' => 'paypal',
            'payment_status' => 'pending',
            'status' => 'pending_payment',
            'claimed_at' => now(),
            'start_date' => null,
            'end_date' => null,
        ]);

        $campaignSlot->forceFill([
            'claim_status' => 'pending_payment',
            'claimed_by_user_id' => $user->id,
        ])->save();

        try {
            $order = $this->createPaypalOrder($provider, $campaign, $option);
        } catch (RequestException $exception) {
            $campaign->delete();
            $campaignSlot->forceFill([
                'claim_status' => 'open',
                'claimed_by_user_id' => null,
            ])->save();

            abort(422, $exception->response?->json('message') ?: 'PayPal checkout could not be started.');
        }

        $campaign->forceFill([
            'payment_reference' => $order['id'] ?? null,
            'payment_metadata' => $order,
        ])->save();

        $approvalLink = collect($order['links'] ?? [])->firstWhere('rel', 'approve');

        return response()->json([
            'campaign' => $this->campaignPayload($campaign->refresh()),
            'checkout_url' => is_array($approvalLink) ? ($approvalLink['href'] ?? null) : null,
        ], 201);
    }

    public function capture(Request $request, DjFeaturedStatus $campaign): JsonResponse
    {
        abort_unless($campaign->djProfile?->user_id === $request->user()->id, 403);
        abort_unless($campaign->payment_provider === 'paypal' && $campaign->payment_reference, 422, 'This campaign does not have a PayPal order to capture.');

        $provider = $this->primaryPaymentProvider();
        abort_unless($provider && $provider->provider === 'paypal', 422, 'PayPal is not active.');

        try {
            $capture = $this->capturePaypalOrder($provider, $campaign->payment_reference);
        } catch (RequestException $exception) {
            abort(422, $exception->response?->json('message') ?: 'PayPal payment could not be captured.');
        }

        $option = $campaign->campaignOption;
        $start = now();
        $end = $start->copy()->addDays((int) ($option?->duration_days ?? 1));

        $campaign->forceFill([
            'payment_status' => 'paid',
            'status' => 'active',
            'start_date' => $start,
            'end_date' => $end,
            'payment_metadata' => [
                ...($campaign->payment_metadata ?? []),
                'capture' => $capture,
            ],
        ])->save();

        $campaign->campaignSlot?->forceFill(['claim_status' => 'claimed'])->save();

        return response()->json([
            'campaign' => $this->campaignPayload($campaign->refresh()),
        ]);
    }

    private function campaignOptionsForSlot(int $templateSlotNumber, int $dailyPrice): array
    {
        $configuredOptionIds = DB::table('featured_slot_campaign_option_slot')
            ->where('slot_number', $templateSlotNumber)
            ->pluck('featured_slot_campaign_option_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (count($configuredOptionIds) === 0) {
            return [];
        }

        return FeaturedSlotCampaignOption::query()
            ->whereIn('id', $configuredOptionIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('duration_days')
            ->get()
            ->map(fn (FeaturedSlotCampaignOption $option): array => [
                'id' => $option->id,
                'name' => $option->name,
                'description' => $option->description,
                'duration_days' => $option->duration_days,
                'price_cents' => $dailyPrice * $option->duration_days,
                'price_label' => $this->formatMoney($dailyPrice * $option->duration_days),
            ])
            ->values()
            ->all();
    }

    private function campaignSlotHasOption(FeaturedCampaignSlot $campaignSlot, int $optionId): bool
    {
        $templateSlotNumber = $this->templateSlotNumber(
            (int) $campaignSlot->campaign->slotGroup->sort_order,
            (int) $campaignSlot->group_slot_number,
        );

        return DB::table('featured_slot_campaign_option_slot')
            ->where('slot_number', $templateSlotNumber)
            ->where('featured_slot_campaign_option_id', $optionId)
            ->exists();
    }

    private function campaignSlotHasActiveOrPendingCampaign(FeaturedCampaignSlot $campaignSlot): bool
    {
        if ($campaignSlot->claim_status !== 'open') {
            return true;
        }

        return DjFeaturedStatus::query()
            ->where('featured_campaign_slot_id', $campaignSlot->id)
            ->whereIn('status', ['active', 'pending_payment'])
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->exists();
    }

    private function primaryPaymentProvider(): ?PaymentProvider
    {
        $activeProviders = PaymentProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('display_name')
            ->get();

        return $activeProviders->firstWhere('is_primary', true) ?? $activeProviders->first();
    }

    private function primaryPaymentProviderPayload(): ?array
    {
        $provider = $this->primaryPaymentProvider();

        return $provider ? [
            'provider' => $provider->provider,
            'display_name' => $provider->display_name,
            'mode' => $provider->mode,
            'credentials_ready' => $provider->hasEffectiveValueFor('client_id') && $provider->hasEffectiveSecret(),
        ] : null;
    }

    private function createPaypalOrder(PaymentProvider $provider, DjFeaturedStatus $campaign, FeaturedSlotCampaignOption $option): array
    {
        $accessToken = $this->paypalAccessToken($provider);
        $baseUrl = $this->paypalBaseUrl($provider);
        $amount = number_format($campaign->amount_cents / 100, 2, '.', '');

        return Http::withToken($accessToken)
            ->acceptJson()
            ->post($baseUrl.'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'featured-campaign-'.$campaign->id,
                    'description' => "BlendBeats Featured Placement - {$option->name}",
                    'amount' => [
                        'currency_code' => $campaign->currency,
                        'value' => $amount,
                    ],
                ]],
                'application_context' => [
                    'brand_name' => 'The Blend Battlegrounds',
                    'landing_page' => 'LOGIN',
                    'user_action' => 'PAY_NOW',
                    'return_url' => url("/account/featured-ads?campaign={$campaign->id}&payment=paypal-return"),
                    'cancel_url' => url("/account/featured-ads?campaign={$campaign->id}&payment=cancelled"),
                ],
            ])
            ->throw()
            ->json();
    }

    private function capturePaypalOrder(PaymentProvider $provider, string $orderId): array
    {
        $accessToken = $this->paypalAccessToken($provider);

        return Http::withToken($accessToken)
            ->acceptJson()
            ->withBody('', 'application/json')
            ->post($this->paypalBaseUrl($provider)."/v2/checkout/orders/{$orderId}/capture")
            ->throw()
            ->json();
    }

    private function paypalAccessToken(PaymentProvider $provider): string
    {
        $response = Http::asForm()
            ->withBasicAuth((string) $provider->effectiveValueFor('client_id'), (string) $provider->effectiveValueFor('secret'))
            ->post($this->paypalBaseUrl($provider).'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ])
            ->throw()
            ->json();

        return (string) $response['access_token'];
    }

    private function paypalBaseUrl(PaymentProvider $provider): string
    {
        return $provider->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function campaignPayload(DjFeaturedStatus $campaign): array
    {
        $campaignSlot = $campaign->campaignSlot;
        $marketplaceCampaign = $campaignSlot?->campaign;
        $slotGroup = $marketplaceCampaign?->slotGroup;

        return [
            'id' => $campaign->id,
            'slot_number' => $campaign->slot_number,
            'campaign_title' => $marketplaceCampaign?->title,
            'campaign_slot_id' => $campaignSlot?->id,
            'group_slot_number' => $campaignSlot?->group_slot_number,
            'group' => $slotGroup?->group_key ?? $this->groupKeyForNumber($this->groupNumberForSlot((int) $campaign->slot_number)),
            'option_name' => $campaign->campaignOption?->name,
            'duration_days' => $campaign->campaignOption?->duration_days,
            'amount_cents' => $campaign->amount_cents,
            'amount_label' => $this->formatMoney($campaign->amount_cents),
            'currency' => $campaign->currency,
            'payment_provider' => $campaign->payment_provider,
            'payment_status' => $campaign->payment_status,
            'status' => $campaign->status,
            'start_date' => $this->dateString($campaign->start_date),
            'end_date' => $this->dateString($campaign->end_date),
            'dj' => $campaign->relationLoaded('djProfile') && $campaign->djProfile ? [
                'name' => $campaign->djProfile->dj_name,
                'handle' => $campaign->djProfile->handle,
            ] : null,
        ];
    }

    private function marketplaceCampaignPayload(FeaturedCampaign $campaign, array $availableGroups): array
    {
        $slotGroup = $campaign->slotGroup;
        $groupKey = (string) $slotGroup->group_key;
        $dailyPrice = (int) $slotGroup->daily_price_cents;
        $isUnlocked = in_array($groupKey, $availableGroups, true);

        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'group' => $groupKey,
            'group_name' => $slotGroup->name,
            'group_number' => (int) $slotGroup->sort_order,
            'template_type' => $slotGroup->template_type,
            'slot_count' => (int) $slotGroup->slot_count,
            'daily_price_cents' => $dailyPrice,
            'daily_price_label' => $this->formatMoney($dailyPrice),
            'is_unlocked' => $isUnlocked,
            'slots' => $campaign->slots
                ->sortBy('group_slot_number')
                ->map(function (FeaturedCampaignSlot $slot) use ($campaign, $slotGroup, $dailyPrice, $isUnlocked): array {
                    $featuredStatus = $slot->featuredStatus;
                    $templateSlotNumber = $this->templateSlotNumber((int) $slotGroup->sort_order, (int) $slot->group_slot_number);
                    $options = $this->campaignOptionsForSlot($templateSlotNumber, $dailyPrice);

                    return [
                        'id' => $slot->id,
                        'campaign_id' => $campaign->id,
                        'group' => $slotGroup->group_key,
                        'group_number' => (int) $slotGroup->sort_order,
                        'group_slot_number' => (int) $slot->group_slot_number,
                        'template_slot_number' => $templateSlotNumber,
                        'claim_status' => $slot->claim_status,
                        'is_unlocked' => $isUnlocked,
                        'is_available' => $slot->claim_status === 'open' && count($options) > 0 && ! $featuredStatus,
                        'active_campaign' => $featuredStatus ? $this->campaignPayload($featuredStatus) : null,
                        'options' => $options,
                    ];
                })
                ->values(),
        ];
    }

    private function groupNumberForSlot(int $slotNumber): int
    {
        return intdiv($slotNumber - 1, self::GROUP_SIZE) + 1;
    }

    private function groupKeyForNumber(int $groupNumber): string
    {
        return chr(64 + $groupNumber);
    }

    private function templateSlotNumber(int $groupNumber, int $groupSlotNumber): int
    {
        return (($groupNumber - 1) * self::GROUP_SIZE) + $groupSlotNumber;
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

    private function dateString(null|string|Carbon $date): ?string
    {
        return $date instanceof Carbon ? $date->toISOString() : $date;
    }
}
