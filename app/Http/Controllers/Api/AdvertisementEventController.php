<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvertisementEvent;
use App\Models\DjFeaturedStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdvertisementEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ad_id' => ['required', 'integer', 'min:1'],
            'ad_type' => ['required', 'string', Rule::in(['dj_promotion'])],
            'event_type' => ['required', 'string', Rule::in(['impression', 'click'])],
            'placement' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        $modelClass = $this->modelClassForType($validated['ad_type']);
        abort_unless($modelClass, 422, 'Unsupported advertisement type.');

        $ad = $modelClass::query()->findOrFail($validated['ad_id']);
        $counterColumn = $validated['event_type'] === 'click' ? 'click_count' : 'impression_count';

        DB::transaction(function () use ($request, $validated, $modelClass, $ad, $counterColumn): void {
            AdvertisementEvent::query()->create([
                'advertisable_type' => $modelClass,
                'advertisable_id' => $ad->id,
                'event_type' => $validated['event_type'],
                'placement' => $validated['placement'] ?? null,
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'ip_hash' => $request->ip() ? hash('sha256', $request->ip()) : null,
                'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $ad->newQuery()
                ->whereKey($ad->id)
                ->increment($counterColumn);
        });

        return response()->json(['tracked' => true]);
    }

    private function modelClassForType(string $type): ?string
    {
        return match ($type) {
            'dj_promotion' => DjFeaturedStatus::class,
            default => null,
        };
    }
}
