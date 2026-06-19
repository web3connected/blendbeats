<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\NewsAutomationLog;
use App\Models\NewsAutomationRule;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutomationNewsApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-automation-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'automation.api_token' => self::TOKEN,
            'automation.news_enabled' => true,
            'automation.default_author_id' => null,
            'logging.default' => 'null',
        ]);

        Log::setDefaultDriver('null');
    }

    public function test_missing_token_returns_401(): void
    {
        $this->getJson('/api/automation/news/rules')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid automation token.');
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->getJson('/api/automation/news/rules', $this->automationHeaders('wrong-token'))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid automation token.');
    }

    public function test_automation_disabled_returns_403(): void
    {
        config(['automation.news_enabled' => false]);

        $this->getJson('/api/automation/news/rules', $this->automationHeaders())
            ->assertForbidden()
            ->assertJsonPath('message', 'Automation is disabled.');
    }

    public function test_valid_token_can_read_rules(): void
    {
        NewsAutomationRule::query()->create([
            'name' => 'Inactive Rule',
            'slug' => 'inactive-rule',
            'rule_type' => 'rss_intake',
            'source_type' => 'rss',
            'priority' => 99,
            'is_active' => false,
        ]);

        NewsAutomationRule::query()->create([
            'name' => 'BlendNews RSS Draft Intake',
            'slug' => 'blendnews-rss-draft-intake',
            'rule_type' => 'rss_intake',
            'source_type' => 'rss',
            'cooldown_minutes' => 30,
            'priority' => 1,
            'is_active' => true,
            'metadata' => [
                'draft_status' => 'review',
                'workflow_name' => 'BlendNews RSS Draft Intake',
            ],
        ]);

        $this->getJson('/api/automation/news/rules', $this->automationHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'blendnews-rss-draft-intake')
            ->assertJsonPath('data.0.rule_type', 'rss_intake');
    }

    public function test_valid_token_can_create_rss_draft(): void
    {
        $author = User::factory()->create();
        config(['automation.default_author_id' => $author->id]);

        $this->postJson('/api/automation/news/rss-drafts', $this->rssPayload(), $this->automationHeaders())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Automation Draft Title')
            ->assertJsonPath('data.status', Post::STATUS_REVIEW)
            ->assertJsonPath('data.author_id', $author->id);

        $post = Post::query()->where('title', 'Automation Draft Title')->firstOrFail();

        $this->assertSame(Post::TYPE_NEWS, $post->content_type);
        $this->assertSame(Post::STATUS_REVIEW, $post->status);
        $this->assertSame('rss-guid-1', $post->metadata['source_guid']);
        $this->assertSame('https://example.com/story-1', $post->metadata['source_url']);
    }

    public function test_duplicate_rss_source_skips(): void
    {
        $payload = $this->rssPayload();

        $this->postJson('/api/automation/news/rss-drafts', $payload, $this->automationHeaders())
            ->assertCreated();

        $this->postJson('/api/automation/news/rss-drafts', [
            ...$payload,
            'title' => 'Duplicate Draft Title',
        ], $this->automationHeaders())
            ->assertOk()
            ->assertJsonPath('skipped', true)
            ->assertJsonPath('reason', 'duplicate_source');

        $this->assertSame(1, Post::query()->count());
        $this->assertDatabaseHas('news_automation_logs', [
            'status' => 'skipped',
            'message' => 'RSS draft skipped: duplicate source.',
        ]);
    }

    public function test_automation_log_is_created(): void
    {
        $this->postJson('/api/automation/news/logs', [
            'workflow_name' => 'BlendNews RSS Draft Intake',
            'status' => 'started',
            'message' => 'Workflow started.',
            'payload' => [
                'source' => 'test',
            ],
        ], $this->automationHeaders())
            ->assertCreated()
            ->assertJsonPath('data.status', 'started')
            ->assertJsonPath('data.message', 'Workflow started.');

        $this->assertDatabaseHas('news_automation_logs', [
            'workflow_name' => 'BlendNews RSS Draft Intake',
            'status' => 'started',
            'message' => 'Workflow started.',
        ]);
    }

    public function test_status_cannot_be_published(): void
    {
        $this->postJson('/api/automation/news/rss-drafts', $this->rssPayload([
            'status' => Post::STATUS_PUBLISHED,
        ]), $this->automationHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Automation may only create draft or review posts.');

        $this->assertSame(0, Post::query()->count());
        $this->assertDatabaseHas('news_automation_logs', [
            'status' => 'failed',
            'message' => 'RSS draft rejected: invalid status.',
        ]);
    }

    public function test_notifications_endpoint_creates_a_log(): void
    {
        Admin::query()->create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);

        $this->postJson('/api/automation/news/notifications', [
            'workflow_name' => 'BlendNews RSS Draft Intake',
            'status' => 'success',
            'message' => 'Automation finished.',
            'payload' => [
                'items_processed' => 2,
            ],
        ], $this->automationHeaders())
            ->assertOk()
            ->assertJsonPath('mode', 'sent')
            ->assertJsonPath('sent', true)
            ->assertJsonPath('recipient_count', 1);

        $this->assertDatabaseHas('news_automation_logs', [
            'workflow_name' => 'BlendNews RSS Draft Intake',
            'status' => 'success',
            'message' => 'Automation notification sent to admin recipients.',
        ]);

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notifications_endpoint_handles_missing_notifications_table_safely(): void
    {
        Schema::dropIfExists('notifications');

        try {
            $response = $this->postJson('/api/automation/news/notifications', [
                'workflow_name' => 'BlendNews RSS Draft Intake',
                'status' => 'skipped',
                'message' => 'No matching RSS items.',
            ], $this->automationHeaders());
        } finally {
            $this->createNotificationsTable();
        }

        $response
            ->assertOk()
            ->assertJsonPath('mode', 'log-only')
            ->assertJsonPath('sent', false)
            ->assertJsonPath('reason', 'notifications_table_missing');

        $this->assertDatabaseHas('news_automation_logs', [
            'workflow_name' => 'BlendNews RSS Draft Intake',
            'status' => 'skipped',
            'message' => 'Automation notification logged without sending: notifications table is not available.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function automationHeaders(string $token = self::TOKEN): array
    {
        return [
            'Authorization' => "Bearer {$token}",
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rssPayload(array $overrides = []): array
    {
        return [
            'source_name' => 'Example RSS',
            'source_url' => 'https://example.com/story-1',
            'source_feed_url' => 'https://example.com/feed',
            'source_guid' => 'rss-guid-1',
            'original_title' => 'Original RSS Title',
            'title' => 'Automation Draft Title',
            'summary' => 'Short BlendNews summary.',
            'status' => 'needs_review',
            'content_type' => Post::TYPE_NEWS,
            'category_slug' => null,
            'metadata' => [
                'created_by_automation' => true,
                'workflow_name' => 'BlendNews RSS Draft Intake',
            ],
            ...$overrides,
        ];
    }

    private function createNotificationsTable(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
}
