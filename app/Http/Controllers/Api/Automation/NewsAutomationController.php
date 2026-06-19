<?php

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\NewsAutomationLog;
use App\Models\NewsAutomationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class NewsAutomationController extends Controller
{
    public function rules(): JsonResponse
    {
        if (! Schema::hasTable('news_automation_rules')) {
            return response()->json(['data' => []]);
        }

        $rules = NewsAutomationRule::query()
            ->active()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $rules->map(fn (NewsAutomationRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'slug' => $rule->slug,
                'rule_type' => $rule->rule_type,
                'event_type' => $rule->event_type,
                'milestone_key' => $rule->milestone_key,
                'source_type' => $rule->source_type,
                'source_id' => $rule->source_id,
                'cooldown_minutes' => $rule->cooldown_minutes,
                'priority' => $rule->priority,
                'metadata' => $rule->metadata ?? [],
            ])->values(),
        ]);
    }

    public function events(): JsonResponse
    {
        return response()->json([
            'data' => [],
        ]);
    }

    public function milestones(): JsonResponse
    {
        return response()->json([
            'data' => [],
        ]);
    }

    public function drafts(): JsonResponse
    {
        return response()->json([
            'message' => 'Automation draft creation is not implemented yet.',
        ], JsonResponse::HTTP_NOT_IMPLEMENTED);
    }

    public function rssDrafts(): JsonResponse
    {
        return response()->json([
            'message' => 'Automation RSS draft creation is not implemented yet.',
        ], JsonResponse::HTTP_NOT_IMPLEMENTED);
    }

    public function logs(Request $request): JsonResponse
    {
        if (! Schema::hasTable('news_automation_logs')) {
            return response()->json([
                'message' => 'Automation logs table is not available.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        $validated = $request->validate([
            'workflow_name' => ['nullable', 'string'],
            'rule_id' => ['nullable', 'integer'],
            'event_id' => ['nullable', 'integer'],
            'status' => ['required', 'string', Rule::in(['success', 'failed', 'skipped', 'started'])],
            'message' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'error_message' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'finished_at' => ['nullable', 'date'],
        ]);

        $log = NewsAutomationLog::query()->create($validated);

        return response()->json([
            'data' => [
                'id' => $log->id,
                'workflow_name' => $log->workflow_name,
                'rule_id' => $log->rule_id,
                'event_id' => $log->event_id,
                'status' => $log->status,
                'message' => $log->message,
                'payload' => $log->payload ?? [],
                'error_message' => $log->error_message,
                'started_at' => $log->started_at?->toISOString(),
                'finished_at' => $log->finished_at?->toISOString(),
                'created_at' => $log->created_at?->toISOString(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function notifications(): JsonResponse
    {
        return response()->json([
            'message' => 'Automation notifications are not implemented yet.',
        ], JsonResponse::HTTP_NOT_IMPLEMENTED);
    }
}
