<?php

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Category;
use App\Models\NewsAutomationLog;
use App\Models\NewsAutomationProcessedMilestone;
use App\Models\NewsAutomationRule;
use App\Models\NewsEvent;
use App\Models\NewsSource;
use App\Models\Post;
use App\Models\User;
use App\Notifications\BlendNewsAutomationDraftCreatedNotification;
use App\Notifications\BlendNewsAutomationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

    public function drafts(Request $request): JsonResponse
    {
        if (! Schema::hasTable('posts')) {
            return response()->json([
                'message' => 'Posts table is not available.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! Schema::hasTable('news_automation_logs')) {
            return response()->json([
                'message' => 'Automation logs table is not available.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! Schema::hasTable('news_automation_processed_milestones')) {
            return response()->json([
                'message' => 'Automation processed milestones table is not available.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'event_type' => ['nullable', 'string', 'max:255'],
            'milestone_key' => ['required', 'string', 'max:255'],
            'source_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:5'],
            'status' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'metadata.workflow_name' => ['nullable', 'string', 'max:255'],
            'created_by_automation' => ['nullable', 'boolean'],
            'rule_id' => ['nullable', 'integer'],
            'event_id' => ['nullable', 'integer'],
        ]);

        $workflowName = (string) (($validated['metadata']['workflow_name'] ?? null) ?: 'BlendNews General Draft Automation');
        $status = $this->normalizeIncomingAutomationDraftStatus($validated['status'] ?? 'needs_review');

        if (! $status) {
            $this->createAutomationLog(
                $workflowName,
                'failed',
                'Automation draft rejected: invalid status.',
                $this->automationPayload($validated),
                'Automation may only create draft or review posts.',
            );

            return response()->json([
                'message' => 'Automation may only create draft or review posts.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rule = $this->resolveAutomationRule($validated['rule_id'] ?? null);

        if (($validated['rule_id'] ?? null) && ! $rule) {
            $this->createAutomationLog(
                $workflowName,
                'failed',
                'Automation draft rejected: rule not found.',
                $this->automationPayload($validated),
                'Automation rule was not found.',
            );

            return response()->json([
                'message' => 'Automation rule was not found.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (($validated['event_id'] ?? null) && ! $this->automationEventExists((int) $validated['event_id'])) {
            $this->createAutomationLog(
                $workflowName,
                'failed',
                'Automation draft rejected: event not found.',
                $this->automationPayload($validated),
                'News event was not found.',
                ruleId: $rule?->id,
            );

            return response()->json([
                'message' => 'News event was not found.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $eventId = isset($validated['event_id']) ? (int) $validated['event_id'] : null;

        $duplicatePost = Post::query()
            ->news()
            ->where('metadata->milestone_key', $validated['milestone_key'])
            ->first();

        if ($duplicatePost) {
            $this->createAutomationLog(
                $workflowName,
                'skipped',
                'Automation draft skipped: duplicate milestone post.',
                $this->automationPayload($validated, duplicatePost: $duplicatePost),
                ruleId: $rule?->id,
                eventId: $eventId,
            );

            return response()->json([
                'skipped' => true,
                'reason' => 'duplicate_milestone',
                'post_id' => $duplicatePost->id,
            ]);
        }

        $processedMilestone = NewsAutomationProcessedMilestone::query()
            ->where('milestone_key', $validated['milestone_key'])
            ->first();

        if ($processedMilestone) {
            $this->createAutomationLog(
                $workflowName,
                'skipped',
                'Automation draft skipped: milestone already processed.',
                $this->automationPayload($validated),
                ruleId: $rule?->id,
                eventId: $eventId,
            );

            return response()->json([
                'skipped' => true,
                'reason' => 'duplicate_processed_milestone',
                'processed_milestone_id' => $processedMilestone->id,
            ]);
        }

        if ($cooldown = $this->activeCooldown($rule)) {
            $this->createAutomationLog(
                $workflowName,
                'skipped',
                'Automation draft skipped: rule cooldown active.',
                $this->automationPayload($validated),
                ruleId: $rule?->id,
                eventId: $eventId,
            );

            return response()->json([
                'skipped' => true,
                'reason' => 'cooldown_active',
                'cooldown_until' => $cooldown->toISOString(),
            ]);
        }

        $authorId = $this->automationAuthorId();

        if (config('automation.default_author_id') && ! $authorId) {
            $this->createAutomationLog(
                $workflowName,
                'failed',
                'Automation draft rejected: automation default author not found.',
                $this->automationPayload($validated),
                'Automation default author is not valid.',
                ruleId: $rule?->id,
                eventId: $eventId,
            );

            return response()->json([
                'message' => 'Automation default author is not valid.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        ['post' => $post, 'log' => $log, 'processedMilestone' => $processedMilestone] = DB::transaction(function () use ($validated, $workflowName, $status, $rule, $eventId, $authorId): array {
            $metadata = $this->generalDraftMetadata($validated, $workflowName);
            $newsSourceId = $this->automationNewsSourceId($validated['source_type'] ?? null, $validated['source_id'] ?? null);

            $post = Post::query()->create([
                'author_id' => $authorId,
                'news_source_id' => $newsSourceId,
                'news_event_id' => $eventId,
                'content_type' => Post::TYPE_NEWS,
                'title' => $validated['title'],
                'slug' => $this->uniquePostSlug($validated['title']),
                'excerpt' => $validated['summary'],
                'content' => $validated['summary'],
                'status' => $status,
                'is_verified' => false,
                'verification_status' => 'unverified',
                'is_breaking' => false,
                'is_featured' => false,
                'importance_level' => (int) ($validated['priority'] ?? 1),
                'seo' => [
                    'title' => $validated['title'],
                    'description' => $validated['summary'],
                ],
                'metadata' => $metadata,
                'reviewed_at' => $status === Post::STATUS_REVIEW ? now() : null,
            ]);

            $processedMilestone = NewsAutomationProcessedMilestone::query()->create([
                'rule_id' => $rule?->id,
                'event_id' => $eventId,
                'milestone_key' => $validated['milestone_key'],
                'source_type' => $validated['source_type'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
                'post_id' => $post->id,
                'processed_at' => now(),
                'metadata' => [
                    'workflow_name' => $workflowName,
                    'created_by_automation' => true,
                    'event_type' => $validated['event_type'] ?? null,
                    'priority' => (int) ($validated['priority'] ?? 1),
                    'payload_metadata' => $validated['metadata'] ?? [],
                ],
            ]);

            $rule?->forceFill([
                'last_checked_at' => now(),
                'last_triggered_at' => now(),
            ])->save();

            $log = $this->createAutomationLog(
                $workflowName,
                'success',
                'Automation draft created.',
                $this->automationPayload($validated, post: $post),
                ruleId: $rule?->id,
                eventId: $eventId,
            );

            return [
                'post' => $post->refresh(),
                'log' => $log,
                'processedMilestone' => $processedMilestone,
            ];
        });

        $this->notifyAdminsOfAutomationDraft($post);

        return response()->json([
            'data' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'content_type' => $post->content_type,
                'author_id' => $post->author_id,
                'news_event_id' => $post->news_event_id,
                'news_source_id' => $post->news_source_id,
                'excerpt' => $post->excerpt,
                'metadata' => $post->metadata ?? [],
                'created_at' => $post->created_at?->toISOString(),
            ],
            'processed_milestone' => [
                'id' => $processedMilestone->id,
                'milestone_key' => $processedMilestone->milestone_key,
            ],
            'automation_log' => [
                'id' => $log?->id,
                'status' => $log?->status,
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function rssDrafts(Request $request): JsonResponse
    {
        if (! Schema::hasTable('posts')) {
            return response()->json([
                'message' => 'Posts table is not available.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! Schema::hasTable('news_automation_logs')) {
            return response()->json([
                'message' => 'Automation logs table is not available.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        $validated = $request->validate([
            'source_name' => ['required', 'string', 'max:255'],
            'source_url' => ['required', 'url', 'max:2048'],
            'source_feed_url' => ['nullable', 'url', 'max:2048'],
            'source_guid' => ['required', 'string', 'max:2048'],
            'original_title' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'max:50'],
            'content_type' => ['nullable', 'string', Rule::in([Post::TYPE_NEWS])],
            'category_slug' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'metadata.workflow_name' => ['nullable', 'string', 'max:255'],
        ]);

        $workflowName = (string) (($validated['metadata']['workflow_name'] ?? null) ?: 'BlendNews RSS Draft Intake');
        $ruleId = $this->rssAutomationRuleId();
        $status = $this->normalizeAutomationPostStatus($validated['status'] ?? Post::STATUS_REVIEW);

        if (! $status) {
            $this->createAutomationLog(
                $workflowName,
                'failed',
                'RSS draft rejected: invalid status.',
                $this->automationPayload($validated),
                'Automation may only create draft or review posts.',
                ruleId: $ruleId,
            );

            return response()->json([
                'message' => 'Automation may only create draft or review posts.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $duplicate = Post::query()
            ->news()
            ->where(function ($query) use ($validated): void {
                $query
                    ->where('metadata->source_guid', $validated['source_guid'])
                    ->orWhere('metadata->source_url', $validated['source_url']);
            })
            ->first();

        if ($duplicate) {
            $this->createAutomationLog(
                $workflowName,
                'skipped',
                'RSS draft skipped: duplicate source.',
                $this->automationPayload($validated, duplicatePost: $duplicate),
                ruleId: $ruleId,
            );

            return response()->json([
                'skipped' => true,
                'reason' => 'duplicate_source',
            ]);
        }

        $category = $this->resolveNewsCategory($validated['category_slug'] ?? null);

        if (($validated['category_slug'] ?? null) && ! $category) {
            $this->createAutomationLog(
                $workflowName,
                'failed',
                'RSS draft rejected: category not found.',
                $this->automationPayload($validated),
                'Category slug was not found.',
                ruleId: $ruleId,
            );

            return response()->json([
                'message' => 'Category slug was not found.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $authorId = $this->automationAuthorId();

        if (config('automation.default_author_id') && ! $authorId) {
            $this->createAutomationLog(
                $workflowName,
                'failed',
                'RSS draft rejected: automation default author not found.',
                $this->automationPayload($validated),
                'Automation default author is not valid.',
                ruleId: $ruleId,
            );

            return response()->json([
                'message' => 'Automation default author is not valid.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        ['post' => $post, 'log' => $log] = DB::transaction(function () use ($validated, $workflowName, $ruleId, $status, $category, $authorId): array {
            $source = $this->resolveNewsSource($validated);
            $metadata = $this->rssPostMetadata($validated, $workflowName);

            $post = Post::query()->create([
                'author_id' => $authorId,
                'category_id' => $category?->id,
                'news_source_id' => $source?->id,
                'content_type' => Post::TYPE_NEWS,
                'title' => $validated['title'],
                'slug' => $this->uniquePostSlug($validated['title']),
                'excerpt' => $validated['summary'],
                'content' => $validated['summary'],
                'status' => $status,
                'is_verified' => false,
                'verification_status' => 'unverified',
                'is_breaking' => false,
                'is_featured' => false,
                'importance_level' => 1,
                'seo' => [
                    'title' => $validated['title'],
                    'description' => $validated['summary'],
                ],
                'metadata' => $metadata,
                'reviewed_at' => $status === Post::STATUS_REVIEW ? now() : null,
            ]);

            if ($category && Schema::hasTable('category_post')) {
                $post->categories()->sync([$category->id]);
            }

            $log = $this->createAutomationLog(
                $workflowName,
                'success',
                'RSS draft created.',
                $this->automationPayload($validated, post: $post),
                ruleId: $ruleId,
            );

            return [
                'post' => $post->refresh(),
                'log' => $log,
            ];
        });

        return response()->json([
            'data' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'content_type' => $post->content_type,
                'author_id' => $post->author_id,
                'category_id' => $post->category_id,
                'news_source_id' => $post->news_source_id,
                'excerpt' => $post->excerpt,
                'metadata' => $post->metadata ?? [],
                'created_at' => $post->created_at?->toISOString(),
            ],
            'automation_log' => [
                'id' => $log?->id,
                'status' => $log?->status,
            ],
        ], JsonResponse::HTTP_CREATED);
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

    public function notifications(Request $request): JsonResponse
    {
        if (! Schema::hasTable('news_automation_logs')) {
            return response()->json([
                'message' => 'Automation logs table is not available.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        $validated = $request->validate([
            'workflow_name' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(['success', 'failed', 'skipped', 'started'])],
            'message' => ['required', 'string'],
            'post_id' => ['nullable', 'integer'],
            'rule_id' => ['nullable', 'integer'],
            'event_id' => ['nullable', 'integer'],
            'payload' => ['nullable', 'array'],
        ]);

        $workflowName = (string) (($validated['workflow_name'] ?? null) ?: 'BlendNews Automation');
        $payload = $this->automationNotificationPayload($validated);
        $ruleId = isset($validated['rule_id']) ? (int) $validated['rule_id'] : null;
        $eventId = isset($validated['event_id']) ? (int) $validated['event_id'] : null;

        if (! Schema::hasTable('notifications')) {
            $log = $this->createAutomationLog(
                $workflowName,
                $validated['status'],
                'Automation notification logged without sending: notifications table is not available.',
                $payload,
                ruleId: $ruleId,
                eventId: $eventId,
            );

            return response()->json([
                'mode' => 'log-only',
                'sent' => false,
                'skipped' => false,
                'reason' => 'notifications_table_missing',
                'automation_log' => [
                    'id' => $log->id,
                    'status' => $log->status,
                ],
            ]);
        }

        $admins = $this->automationNotificationAdmins();

        if ($admins->isEmpty()) {
            $log = $this->createAutomationLog(
                $workflowName,
                'skipped',
                'Automation notification skipped: no active admin recipients found.',
                $payload,
                ruleId: $ruleId,
                eventId: $eventId,
            );

            return response()->json([
                'mode' => 'skipped',
                'sent' => false,
                'skipped' => true,
                'reason' => 'no_admin_recipients',
                'automation_log' => [
                    'id' => $log->id,
                    'status' => $log->status,
                ],
            ]);
        }

        foreach ($admins as $admin) {
            $admin->notify(new BlendNewsAutomationNotification($payload));
        }

        $log = $this->createAutomationLog(
            $workflowName,
            $validated['status'],
            'Automation notification sent to admin recipients.',
            [
                ...$payload,
                'recipient_count' => $admins->count(),
            ],
            ruleId: $ruleId,
            eventId: $eventId,
        );

        return response()->json([
            'mode' => 'sent',
            'sent' => true,
            'skipped' => false,
            'recipient_count' => $admins->count(),
            'automation_log' => [
                'id' => $log->id,
                'status' => $log->status,
            ],
        ]);
    }

    private function normalizeAutomationPostStatus(string $status): ?string
    {
        if ($status === 'needs_review') {
            $status = Post::STATUS_REVIEW;
        }

        return in_array($status, [Post::STATUS_DRAFT, Post::STATUS_REVIEW], true) ? $status : null;
    }

    private function normalizeIncomingAutomationDraftStatus(string $status): ?string
    {
        if ($status === 'needs_review') {
            return Post::STATUS_REVIEW;
        }

        return $status === Post::STATUS_DRAFT ? Post::STATUS_DRAFT : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function generalDraftMetadata(array $validated, string $workflowName): array
    {
        return array_merge($validated['metadata'] ?? [], [
            'created_by_automation' => true,
            'workflow_name' => $workflowName,
            'event_type' => $validated['event_type'] ?? null,
            'milestone_key' => $validated['milestone_key'],
            'source_type' => $validated['source_type'] ?? null,
            'source_id' => $validated['source_id'] ?? null,
            'priority' => (int) ($validated['priority'] ?? 1),
            'automation' => [
                'endpoint' => 'drafts',
                'received_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function rssPostMetadata(array $validated, string $workflowName): array
    {
        return array_merge($validated['metadata'] ?? [], [
            'created_by_automation' => true,
            'workflow_name' => $workflowName,
            'source_name' => $validated['source_name'],
            'source_url' => $validated['source_url'],
            'source_feed_url' => $validated['source_feed_url'] ?? null,
            'source_guid' => $validated['source_guid'],
            'original_title' => $validated['original_title'] ?? null,
            'automation' => [
                'endpoint' => 'rss-drafts',
                'received_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveNewsSource(array $validated): ?NewsSource
    {
        if (! Schema::hasTable('news_sources')) {
            return null;
        }

        $sourceUrl = $validated['source_feed_url'] ?? $validated['source_url'];
        $slug = Str::slug($validated['source_name']);

        if ($slug === '') {
            $slug = 'rss-source-'.substr(md5($sourceUrl), 0, 8);
        }

        $source = NewsSource::query()
            ->where('url', $sourceUrl)
            ->orWhere('slug', $slug)
            ->first();

        if ($source) {
            return $source;
        }

        return NewsSource::query()->create([
            'name' => $validated['source_name'],
            'slug' => $slug,
            'url' => $sourceUrl,
            'source_type' => 'rss',
            'credibility_rating' => null,
            'is_active' => true,
            'metadata' => [
                'source_feed_url' => $validated['source_feed_url'] ?? null,
            ],
        ]);
    }

    private function resolveNewsCategory(?string $slug): ?Category
    {
        if (! $slug || ! Schema::hasTable('categories')) {
            return null;
        }

        return Category::query()
            ->news()
            ->active()
            ->where('slug', $slug)
            ->first();
    }

    private function automationAuthorId(): ?int
    {
        $authorId = config('automation.default_author_id');

        if (! $authorId || ! Schema::hasTable('users')) {
            return null;
        }

        return User::query()->whereKey($authorId)->exists() ? (int) $authorId : null;
    }

    private function resolveAutomationRule(?int $ruleId): ?NewsAutomationRule
    {
        if (! $ruleId || ! Schema::hasTable('news_automation_rules')) {
            return null;
        }

        return NewsAutomationRule::query()->find($ruleId);
    }

    private function automationEventExists(int $eventId): bool
    {
        if (! Schema::hasTable('news_events')) {
            return false;
        }

        return NewsEvent::query()->whereKey($eventId)->exists();
    }

    private function activeCooldown(?NewsAutomationRule $rule): ?Carbon
    {
        if (! $rule || ! $rule->last_triggered_at || $rule->cooldown_minutes < 1) {
            return null;
        }

        $cooldownUntil = $rule->last_triggered_at->copy()->addMinutes($rule->cooldown_minutes);

        return $cooldownUntil->isFuture() ? $cooldownUntil : null;
    }

    private function automationNewsSourceId(?string $sourceType, mixed $sourceId): ?int
    {
        if (! $sourceId || ! Schema::hasTable('news_sources')) {
            return null;
        }

        if (! in_array($sourceType, ['news_source', 'news_sources', 'rss'], true)) {
            return null;
        }

        return NewsSource::query()->whereKey($sourceId)->exists() ? (int) $sourceId : null;
    }

    private function notifyAdminsOfAutomationDraft(Post $post): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasTable('notifications')) {
            return;
        }

        Admin::query()
            ->where('is_active', true)
            ->chunkById(50, function ($admins) use ($post): void {
                foreach ($admins as $admin) {
                    $admin->notify(new BlendNewsAutomationDraftCreatedNotification($post));
                }
            });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function automationNotificationPayload(array $validated): array
    {
        $postId = $validated['post_id'] ?? null;

        return array_filter([
            'title' => 'BlendNews automation update',
            'message' => $validated['message'],
            'category' => 'system',
            'action_label' => $postId ? 'Review Post' : null,
            'action_url' => $postId ? route('admin.blendnews.edit', $postId, false) : null,
            'icon' => 'newspaper',
            'workflow_name' => $validated['workflow_name'] ?? null,
            'status' => $validated['status'],
            'post_id' => $postId,
            'rule_id' => $validated['rule_id'] ?? null,
            'event_id' => $validated['event_id'] ?? null,
            'payload' => $validated['payload'] ?? [],
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @return Collection<int, Admin>
     */
    private function automationNotificationAdmins(): Collection
    {
        if (! Schema::hasTable('admins')) {
            return collect();
        }

        return Admin::query()
            ->where('is_active', true)
            ->whereIn('role', ['super-admin', 'sys-admin', 'system-admin', 'admin'])
            ->orderBy('id')
            ->get();
    }

    private function uniquePostSlug(string $title): string
    {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'blendnews-draft';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function automationPayload(array $payload, ?Post $post = null, ?Post $duplicatePost = null): array
    {
        return array_filter([
            'source_name' => $payload['source_name'] ?? null,
            'source_url' => $payload['source_url'] ?? null,
            'source_feed_url' => $payload['source_feed_url'] ?? null,
            'source_guid' => $payload['source_guid'] ?? null,
            'original_title' => $payload['original_title'] ?? null,
            'title' => $payload['title'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'event_type' => $payload['event_type'] ?? null,
            'milestone_key' => $payload['milestone_key'] ?? null,
            'source_id' => $payload['source_id'] ?? null,
            'source_type' => $payload['source_type'] ?? null,
            'priority' => $payload['priority'] ?? null,
            'rule_id' => $payload['rule_id'] ?? null,
            'event_id' => $payload['event_id'] ?? null,
            'created_by_automation' => $payload['created_by_automation'] ?? null,
            'status' => $payload['status'] ?? null,
            'category_slug' => $payload['category_slug'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
            'post_id' => $post?->id,
            'duplicate_post_id' => $duplicatePost?->id,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createAutomationLog(
        string $workflowName,
        string $status,
        string $message,
        array $payload,
        ?string $errorMessage = null,
        ?int $ruleId = null,
        ?int $eventId = null,
    ): NewsAutomationLog {
        return NewsAutomationLog::query()->create([
            'workflow_name' => $workflowName,
            'rule_id' => $ruleId,
            'event_id' => $eventId,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
            'error_message' => $errorMessage,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function rssAutomationRuleId(): ?int
    {
        if (! Schema::hasTable('news_automation_rules')) {
            return null;
        }

        return NewsAutomationRule::query()
            ->where('slug', 'blendnews-rss-draft-intake')
            ->value('id');
    }
}
