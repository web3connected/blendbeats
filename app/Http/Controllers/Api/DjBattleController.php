<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DjBattle;
use App\Models\DjBattleEntry;
use App\Models\DjProfile;
use App\Services\DjBattles\DjBattleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DjBattleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                DjBattle::STATUS_ACCEPTED,
                DjBattle::STATUS_RECORDING,
                DjBattle::STATUS_VOTING,
                DjBattle::STATUS_COMPLETED,
            ])],
            'battle_type' => ['nullable', Rule::in(['mix', 'scratch', 'open_format', 'theme'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $battles = DjBattle::query()
            ->with($this->relations())
            ->publicVisible()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['battle_type'] ?? null, fn ($query, string $type) => $query->where('battle_type', $type))
            ->latest()
            ->limit((int) ($filters['limit'] ?? 50))
            ->get();

        return response()->json([
            'battles' => $battles
                ->map(fn (DjBattle $battle): array => $this->battlePayload($battle))
                ->values(),
        ]);
    }

    public function show(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        $battle = $battles->pauseExpiredChallenge($battle);

        abort_unless($this->canViewBattle($request, $battle), 404);

        return response()->json([
            'battle' => $this->battlePayload($battle),
        ]);
    }

    public function store(Request $request, DjBattleService $battles): JsonResponse
    {
        $attributes = $request->validate([
            'opponent_dj_profile_id' => ['required', 'integer', 'exists:dj_profiles,id'],
            'battle_type' => ['required', Rule::in(['mix', 'scratch', 'open_format', 'theme'])],
            'title' => ['required', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rules' => ['nullable', 'string', 'max:2000'],
            'duration_seconds' => ['nullable', 'integer', 'min:30', 'max:300'],
            'voting_duration_hours' => ['nullable', 'integer', Rule::in([24, 48, 72])],
            'minimum_votes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'stake_amount' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'challenge_message' => ['nullable', 'string', 'max:500'],
        ]);

        $opponent = DjProfile::query()->findOrFail($attributes['opponent_dj_profile_id']);
        $battle = $battles->createChallenge($request->user(), $opponent, $attributes);

        return response()->json([
            'battle' => $this->battlePayload($battle),
        ], 201);
    }

    public function accept(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        return response()->json([
            'battle' => $this->battlePayload($battles->accept($request->user(), $battle)),
        ]);
    }

    public function decline(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        return response()->json([
            'battle' => $this->battlePayload($battles->decline($request->user(), $battle)),
        ]);
    }

    public function cancel(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        return response()->json([
            'battle' => $this->battlePayload($battles->cancel($request->user(), $battle)),
        ]);
    }

    public function extend(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        return response()->json([
            'battle' => $this->battlePayload($battles->extend($request->user(), $battle)),
        ]);
    }

    public function ready(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        return response()->json([
            'battle' => $this->battlePayload($battles->ready($request->user(), $battle)),
        ]);
    }

    public function readyOtherParticipantForTesting(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        return response()->json([
            'battle' => $this->battlePayload($battles->readyOtherParticipantForTesting($request->user(), $battle)),
        ]);
    }

    public function bypassSamplePack(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        return response()->json([
            'battle' => $this->battlePayload($battles->bypassSamplePack($request->user(), $battle)),
        ]);
    }

    public function submitEntry(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        $attributes = $request->validate([
            'media' => ['required', 'file', 'max:512000', 'mimetypes:video/webm,video/mp4,video/quicktime'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:300'],
            'recorded_in_browser' => ['nullable', 'boolean'],
        ]);

        $media = $request->file('media');
        unset($attributes['media']);

        return response()->json([
            'battle' => $this->battlePayload($battles->submitEntry($request->user(), $battle, $media, $attributes)),
        ]);
    }

    public function duplicateEntryForTesting(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        return response()->json([
            'battle' => $this->battlePayload($battles->duplicateSubmittedEntryForTesting($request->user(), $battle)),
        ]);
    }

    public function account(Request $request, DjBattleService $battles): JsonResponse
    {
        return response()->json([
            'battles' => $battles->accountBattles($request->user())
                ->map(fn (DjBattle $battle): array => $this->battlePayload($battle))
                ->values(),
        ]);
    }

    private function canViewBattle(Request $request, DjBattle $battle): bool
    {
        if (in_array($battle->status, [
            DjBattle::STATUS_ACCEPTED,
            DjBattle::STATUS_RECORDING,
            DjBattle::STATUS_VOTING,
            DjBattle::STATUS_COMPLETED,
        ], true)) {
            return true;
        }

        $user = $request->user();

        if (! $user) {
            return false;
        }

        $profile = $user->djProfile()->first();

        return $profile ? $battle->isParticipantProfile($profile) : false;
    }

    private function battlePayload(DjBattle $battle): array
    {
        return [
            'uuid' => $battle->uuid,
            'status' => $battle->status,
            'battle_type' => $battle->battle_type,
            'title' => $battle->title,
            'theme' => $battle->theme,
            'description' => $battle->description,
            'rules' => $battle->rules,
            'duration_seconds' => (int) $battle->duration_seconds,
            'voting_duration_hours' => (int) $battle->voting_duration_hours,
            'minimum_votes' => (int) $battle->minimum_votes,
            'stake_amount' => (int) $battle->stake_amount,
            'currency' => $battle->currency,
            'sample_pack_status' => $battle->sample_pack_status,
            'sample_pack_ready_at' => optional($battle->sample_pack_ready_at)->toISOString(),
            'sample_pack_bypassed_at' => optional($battle->sample_pack_bypassed_at)->toISOString(),
            'sample_pack_metadata' => $battle->sample_pack_metadata ?? [],
            'challenge_message' => $battle->challenge_message,
            'fan_reward_pool_amount' => (int) $battle->fan_reward_pool_amount,
            'prize_pool_amount' => (int) $battle->prize_pool_amount,
            'challenger' => $this->profilePayload($battle->challenger),
            'opponent' => $this->profilePayload($battle->opponent),
            'winner' => $battle->winner ? $this->profilePayload($battle->winner) : null,
            'readiness' => [
                'challenger_ready' => (bool) $battle->challenger_ready_at,
                'opponent_ready' => (bool) $battle->opponent_ready_at,
                'both_ready' => (bool) $battle->challenger_ready_at && (bool) $battle->opponent_ready_at,
            ],
            'entries' => $battle->entries
                ->map(fn (DjBattleEntry $entry): array => $this->entryPayload($entry))
                ->values(),
            'result' => $battle->result ? [
                'winner_dj_profile_id' => $battle->result->winner_dj_profile_id,
                'challenger_score' => (float) $battle->result->challenger_score,
                'opponent_score' => (float) $battle->result->opponent_score,
                'total_votes' => (int) $battle->result->total_votes,
                'is_draw' => (bool) $battle->result->is_draw,
                'calculated_at' => optional($battle->result->calculated_at)->toISOString(),
            ] : null,
            'response_due_at' => optional($battle->response_due_at)->toISOString(),
            'ready_due_at' => optional($battle->ready_due_at)->toISOString(),
            'challenger_ready_at' => optional($battle->challenger_ready_at)->toISOString(),
            'opponent_ready_at' => optional($battle->opponent_ready_at)->toISOString(),
            'accepted_at' => optional($battle->accepted_at)->toISOString(),
            'recording_started_at' => optional($battle->recording_started_at)->toISOString(),
            'recording_ends_at' => optional($battle->recording_ends_at)->toISOString(),
            'voting_started_at' => optional($battle->voting_started_at)->toISOString(),
            'voting_ends_at' => optional($battle->voting_ends_at)->toISOString(),
            'completed_at' => optional($battle->completed_at)->toISOString(),
            'declined_at' => optional($battle->declined_at)->toISOString(),
            'cancelled_at' => optional($battle->cancelled_at)->toISOString(),
            'created_at' => optional($battle->created_at)->toISOString(),
        ];
    }

    private function profilePayload(?DjProfile $profile): ?array
    {
        if (! $profile) {
            return null;
        }

        return [
            'id' => (int) $profile->id,
            'dj_name' => $profile->dj_name,
            'handle' => $profile->handle,
            'headline' => $profile->profile_headline,
            'avatar_url' => $profile->user?->getAvatarUrl(),
            'city' => $profile->city,
            'state' => $profile->state,
            'country' => $profile->country,
            'battle_enabled' => (bool) $profile->battle_enabled,
        ];
    }

    private function entryPayload(DjBattleEntry $entry): array
    {
        return [
            'id' => (int) $entry->id,
            'dj_profile_id' => (int) $entry->dj_profile_id,
            'status' => $entry->status,
            'title' => $entry->title,
            'notes' => $entry->notes,
            'duration_seconds' => $entry->duration_seconds ? (int) $entry->duration_seconds : null,
            'media_file_id' => $entry->media_file_id,
            'media_url' => $entry->mediaFile?->url,
            'submitted_at' => optional($entry->submitted_at)->toISOString(),
        ];
    }

    private function relations(): array
    {
        return [
            'challenger.user',
            'opponent.user',
            'winner.user',
            'entries.mediaFile',
            'entries.djProfile',
            'result',
        ];
    }
}
