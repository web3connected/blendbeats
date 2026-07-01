<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DjBattle;
use App\Models\DjBattleEntry;
use App\Models\DjProfile;
use App\Services\DjBattles\DjBattleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DjBattleController extends Controller
{
    public function index(Request $request, DjBattleService $battleService): JsonResponse
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

        DjBattle::query()
            ->where('status', DjBattle::STATUS_VOTING)
            ->whereNotNull('voting_ends_at')
            ->where('voting_ends_at', '<=', now())
            ->limit(50)
            ->get()
            ->each(fn (DjBattle $battle): DjBattle => $battleService->completeExpiredVoting($battle));

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

    public function leaderboards(Request $request, DjBattleService $battleService): JsonResponse
    {
        $categories = $this->leaderboardCategories();
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(array_keys($categories))],
            'period' => ['nullable', Rule::in(['all_time', 'week', 'month', 'season'])],
            'verified' => ['nullable', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
            'active' => ['nullable', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
            'min_battles' => ['nullable', 'integer', 'min:1', 'max:25'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $category = $filters['category'] ?? 'overall';
        $categoryConfig = $categories[$category];
        $minimumBattles = (int) ($filters['min_battles'] ?? 3);
        $limit = (int) ($filters['limit'] ?? 100);
        $period = $filters['period'] ?? 'all_time';

        DjBattle::query()
            ->where('status', DjBattle::STATUS_VOTING)
            ->whereNotNull('voting_ends_at')
            ->where('voting_ends_at', '<=', now())
            ->limit(50)
            ->get()
            ->each(fn (DjBattle $battle): DjBattle => $battleService->completeExpiredVoting($battle));

        $scoreRows = DB::table('dj_battle_vote_scores as scores')
            ->join('dj_battle_votes as votes', 'votes.id', '=', 'scores.vote_id')
            ->join('dj_battles as battles', 'battles.id', '=', 'scores.battle_id')
            ->join('dj_profiles as profiles', 'profiles.id', '=', 'scores.dj_profile_id')
            ->join('users', 'users.id', '=', 'profiles.user_id')
            ->where('battles.status', DjBattle::STATUS_COMPLETED)
            ->whereNotNull('votes.submitted_at')
            ->where('votes.reward_eligible', true)
            ->when($this->periodStart($period), fn ($query, $start) => $query->where('battles.completed_at', '>=', $start))
            ->when($this->queryBoolean($request, 'verified'), fn ($query) => $query->where('profiles.verification_status', 'verified'))
            ->when($this->queryBoolean($request, 'active'), fn ($query) => $query
                ->where('profiles.battle_enabled', true)
                ->where('profiles.profile_status', 'active')
                ->where('profiles.visibility', 'public'))
            ->groupBy([
                'profiles.id',
                'profiles.dj_name',
                'profiles.handle',
                'profiles.profile_headline',
                'profiles.verification_status',
                'profiles.battle_enabled',
                'users.avatar',
                'users.use_gravatar',
                'users.email',
            ])
            ->select([
                'profiles.id',
                'profiles.dj_name',
                'profiles.handle',
                'profiles.profile_headline',
                'profiles.verification_status',
                'profiles.battle_enabled',
                'users.avatar',
                'users.use_gravatar',
                'users.email',
            ])
            ->selectRaw("AVG(scores.{$categoryConfig['column']}) as selected_score")
            ->selectRaw('AVG(scores.total_score) as average_total_score')
            ->selectRaw('COUNT(scores.id) as score_count')
            ->selectRaw('COUNT(DISTINCT scores.battle_id) as scored_battles_count')
            ->selectRaw('MAX(battles.completed_at) as last_battle_date')
            ->orderByDesc('selected_score')
            ->orderByDesc('scored_battles_count')
            ->orderBy('profiles.dj_name')
            ->limit($limit)
            ->get();

        $profileIds = $scoreRows->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $battleStats = $this->leaderboardBattleStats($profileIds, $period);
        $rank = 1;
        $newRank = 1;
        $official = [];
        $newCompetitors = [];

        foreach ($scoreRows as $row) {
            $profileId = (int) $row->id;
            $stats = $battleStats[$profileId] ?? ['completed_battles_count' => 0, 'wins' => 0, 'losses' => 0];
            $payload = [
                'dj_id' => $profileId,
                'dj_name' => $row->dj_name,
                'handle' => $row->handle,
                'headline' => $row->profile_headline,
                'avatar_url' => $this->avatarUrlFromColumns($row),
                'rank' => null,
                'qualified' => (int) $row->scored_battles_count >= $minimumBattles,
                'selected_category' => $category,
                'selected_category_label' => $categoryConfig['label'],
                'selected_category_score' => round((float) $row->selected_score, 2),
                'selected_category_max_score' => (int) $categoryConfig['max_score'],
                'completed_battles_count' => (int) $stats['completed_battles_count'],
                'scored_battles_count' => (int) $row->scored_battles_count,
                'score_count' => (int) $row->score_count,
                'wins' => (int) $stats['wins'],
                'losses' => (int) $stats['losses'],
                'average_total_score' => round((float) $row->average_total_score, 2),
                'last_battle_date' => optional($row->last_battle_date ? \Illuminate\Support\Carbon::parse($row->last_battle_date) : null)->toISOString(),
            ];

            if ($payload['qualified']) {
                $payload['rank'] = $rank++;
                $official[] = $payload;
            } else {
                $payload['rank'] = $newRank++;
                $newCompetitors[] = $payload;
            }
        }

        return response()->json([
            'category' => $category,
            'category_label' => $categoryConfig['label'],
            'categories' => collect($categories)
                ->map(fn (array $item, string $key): array => [
                    'value' => $key,
                    'label' => $item['label'],
                    'max_score' => (int) $item['max_score'],
                ])
                ->values(),
            'period' => $period,
            'minimum_battles' => $minimumBattles,
            'leaderboard' => $official,
            'new_competitors' => $newCompetitors,
        ]);
    }

    public function show(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        $battle = $battles->pauseExpiredChallenge($battle);
        $battle = $battles->completeExpiredVoting($battle);

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

    public function submitVote(Request $request, DjBattle $battle, DjBattleService $battles): JsonResponse
    {
        $scoreRules = [];

        foreach (DjBattleService::VOTE_SCORE_CATEGORIES as $category) {
            $scoreRules["scores.*.scores.{$category}"] = ['required', 'integer', 'min:1', 'max:10'];
        }

        $payload = $request->validate([
            'watch_order' => ['required', 'array', 'size:2'],
            'watch_order.*' => ['required', 'integer', 'distinct', 'exists:dj_profiles,id'],
            'scores' => ['required', 'array', 'size:2'],
            'scores.*.dj_profile_id' => ['required', 'integer', 'distinct', 'exists:dj_profiles,id'],
            'scores.*.scores' => ['required', 'array'],
            ...$scoreRules,
        ]);

        return response()->json([
            'battle' => $this->battlePayload($battles->submitFanVote($request->user(), $battle, $payload)),
        ], 201);
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
            'vote_count' => $battle->votes()->whereNotNull('submitted_at')->count(),
            'viewer_vote' => $this->viewerVotePayload($battle),
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

    private function avatarUrlFromColumns(object $row): string
    {
        $user = new \App\Models\User([
            'avatar' => $row->avatar,
            'use_gravatar' => (bool) $row->use_gravatar,
            'email' => $row->email,
        ]);

        return $user->getAvatarUrl();
    }

    private function leaderboardCategories(): array
    {
        return [
            'overall' => ['label' => 'Overall', 'column' => 'total_score', 'max_score' => 100],
            'sample_integration' => ['label' => 'Sample Integration', 'column' => 'sample_integration_score', 'max_score' => 10],
            'scratching_ability' => ['label' => 'Scratching Ability', 'column' => 'scratching_score', 'max_score' => 10],
            'mixing_ability' => ['label' => 'Mixing Ability', 'column' => 'mixing_score', 'max_score' => 10],
            'blending' => ['label' => 'Blending', 'column' => 'blending_score', 'max_score' => 10],
            'creativity' => ['label' => 'Creativity', 'column' => 'creativity_score', 'max_score' => 10],
            'technical_execution' => ['label' => 'Technical Execution', 'column' => 'technical_execution_score', 'max_score' => 10],
            'music_selection' => ['label' => 'Music Selection', 'column' => 'track_selection_score', 'max_score' => 10],
            'battle_composition' => ['label' => 'Battle Composition', 'column' => 'battle_composition_score', 'max_score' => 10],
            'entertainment_value' => ['label' => 'Entertainment Value', 'column' => 'entertainment_value_score', 'max_score' => 10],
            'overall_performance' => ['label' => 'Overall Performance', 'column' => 'overall_performance_score', 'max_score' => 10],
        ];
    }

    private function periodStart(string $period): ?\Illuminate\Support\Carbon
    {
        return match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'season' => now()->startOfYear(),
            default => null,
        };
    }

    private function queryBoolean(Request $request, string $key): bool
    {
        return filter_var($request->query($key), FILTER_VALIDATE_BOOLEAN);
    }

    private function leaderboardBattleStats(array $profileIds, string $period): array
    {
        if ($profileIds === []) {
            return [];
        }

        $start = $this->periodStart($period);
        $stats = [];

        foreach ($profileIds as $profileId) {
            $base = DjBattle::query()
                ->where('status', DjBattle::STATUS_COMPLETED)
                ->when($start, fn ($query) => $query->where('completed_at', '>=', $start))
                ->where(fn ($query) => $query
                    ->where('challenger_dj_profile_id', $profileId)
                    ->orWhere('opponent_dj_profile_id', $profileId));

            $stats[$profileId] = [
                'completed_battles_count' => (clone $base)->count(),
                'wins' => (clone $base)->where('winner_dj_profile_id', $profileId)->count(),
                'losses' => (clone $base)
                    ->whereNotNull('winner_dj_profile_id')
                    ->where('winner_dj_profile_id', '!=', $profileId)
                    ->count(),
            ];
        }

        return $stats;
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

    private function viewerVotePayload(DjBattle $battle): ?array
    {
        $user = request()->user();

        if (! $user) {
            return null;
        }

        $vote = $battle->votes()
            ->with('scores')
            ->where('user_id', $user->id)
            ->whereNotNull('submitted_at')
            ->first();

        if (! $vote) {
            return null;
        }

        return [
            'id' => (int) $vote->id,
            'reward_eligible' => (bool) $vote->reward_eligible,
            'submitted_at' => optional($vote->submitted_at)->toISOString(),
            'prediction_dj_profile_id' => $vote->prediction_dj_profile_id,
            'scores' => $vote->scores
                ->map(fn ($score): array => [
                    'dj_profile_id' => (int) $score->dj_profile_id,
                    'entry_id' => (int) $score->entry_id,
                    'total_score' => (float) $score->total_score,
                    'category_scores' => $score->metadata['category_scores'] ?? [],
                ])
                ->values(),
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
