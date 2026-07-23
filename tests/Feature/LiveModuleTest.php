<?php

namespace Tests\Feature;

use App\Models\DjProfile;
use App\Models\LiveChannel;
use App\Models\LiveStream;
use App\Models\LiveStreamViewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.agora.app_id' => '1234567890abcdef1234567890abcdef',
            'services.agora.app_certificate' => 'abcdef1234567890abcdef1234567890',
            'services.agora.project_name' => 'BlendBeat Live',
            'services.agora.token_ttl' => 3600,
        ]);
    }

    public function test_live_browser_routes_load_react_shell(): void
    {
        $this->get('/live')
            ->assertOk()
            ->assertSee('id="app"', false);

        $this->get('/live/dj-live')
            ->assertOk()
            ->assertSee('id="app"', false);

        $this->get('/dashboard/live')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_paid_dj_can_start_live_stream_and_receive_host_token(): void
    {
        $user = $this->djUser('dj_plus');

        $response = $this->actingAs($user)
            ->postJson('/api/live/start', [
                'title' => 'Friday Night Blend',
            ])
            ->assertCreated()
            ->assertJsonPath('stream.status', LiveStream::STATUS_LIVE)
            ->assertJsonPath('stream.title', 'Friday Night Blend')
            ->assertJsonPath('stream.max_duration_minutes', 30)
            ->assertJsonPath('stream.recording_enabled', false)
            ->assertJsonPath('stream.channel.username_slug', 'dj-live')
            ->assertJsonPath('token.role', 'host')
            ->assertJsonPath('token.appId', '1234567890abcdef1234567890abcdef');

        $this->assertStringStartsWith('007', $response->json('token.token'));
        $this->assertStringStartsWith('live-dj-live-', $response->json('stream.agora_channel_name'));
        $this->assertStringNotContainsString('abcdef1234567890abcdef1234567890', $response->getContent());

        $this->assertDatabaseHas('live_channels', [
            'user_id' => $user->id,
            'username_slug' => 'dj-live',
            'title' => 'DJ Live',
        ]);

        $this->assertDatabaseHas('live_streams', [
            'user_id' => $user->id,
            'title' => 'Friday Night Blend',
            'status' => LiveStream::STATUS_LIVE,
        ]);
    }

    public function test_agora_configuration_is_validated_before_a_live_stream_is_created(): void
    {
        config(['services.agora.app_certificate' => null]);

        $this->actingAs($this->djUser('dj_plus'))
            ->postJson('/api/live/start')
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Agora is not configured correctly.');

        $this->assertDatabaseCount('live_streams', 0);
    }

    public function test_free_dj_cannot_start_live_stream(): void
    {
        $user = $this->djUser('free');

        $this->actingAs($user)
            ->postJson('/api/live/start')
            ->assertForbidden()
            ->assertJsonPath('message', 'Only paid DJ accounts can go live.');

        $this->assertDatabaseCount('live_streams', 0);
    }

    public function test_plus_user_is_blocked_after_monthly_stream_limit(): void
    {
        $user = $this->djUser('dj_plus');
        $this->createMonthlyStreams($user, 20);

        $this->actingAs($user)
            ->postJson('/api/live/start')
            ->assertForbidden()
            ->assertJsonPath('message', 'You have reached your monthly live stream limit for your plan.');
    }

    public function test_plus_user_cannot_enable_recording(): void
    {
        $user = $this->djUser('dj_plus');

        $this->actingAs($user)
            ->postJson('/api/live/start', [
                'recording_enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('stream.max_duration_minutes', 30)
            ->assertJsonPath('stream.recording_enabled', false)
            ->assertJsonPath('stream.recording_status', null);
    }

    public function test_pro_user_gets_sixty_minutes_and_can_enable_recording(): void
    {
        $user = $this->djUser('dj_pro');

        $this->actingAs($user)
            ->postJson('/api/live/start', [
                'recording_enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('stream.max_duration_minutes', 60)
            ->assertJsonPath('stream.recording_enabled', true)
            ->assertJsonPath('stream.recording_status', 'requested');
    }

    public function test_pro_user_is_blocked_after_monthly_stream_limit(): void
    {
        $user = $this->djUser('dj_pro');
        $this->createMonthlyStreams($user, 50);

        $this->actingAs($user)
            ->postJson('/api/live/start')
            ->assertForbidden()
            ->assertJsonPath('message', 'You have reached your monthly live stream limit for your plan.');
    }

    public function test_elite_user_has_unlimited_count_duration_and_recording(): void
    {
        $user = $this->djUser('dj_elite');
        $this->createMonthlyStreams($user, 75);

        $this->actingAs($user)
            ->postJson('/api/live/start', [
                'recording_enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('stream.max_duration_minutes', null)
            ->assertJsonPath('stream.recording_enabled', true)
            ->assertJsonPath('stream.recording_status', 'requested');
    }

    public function test_user_must_have_dj_profile_to_start_live_stream(): void
    {
        $user = User::factory()->create([
            'media_storage_tier' => 'dj_plus',
        ]);

        $this->actingAs($user)
            ->postJson('/api/live/start')
            ->assertForbidden()
            ->assertJsonPath('message', 'Only paid DJ accounts can go live.');

        $this->assertDatabaseCount('live_streams', 0);
    }

    public function test_dj_cannot_start_multiple_active_streams(): void
    {
        $user = $this->djUser('dj_plus');

        $this->actingAs($user)
            ->postJson('/api/live/start')
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/api/live/start')
            ->assertConflict()
            ->assertJsonPath('message', 'You already have an active live stream.');

        $this->assertDatabaseCount('live_streams', 1);
    }

    public function test_public_live_directory_and_channel_show_active_stream(): void
    {
        $user = $this->djUser('dj_plus');

        $streamId = $this->actingAs($user)
            ->postJson('/api/live/start', ['title' => 'Open Format Live'])
            ->json('stream.id');

        $this->getJson('/api/live')
            ->assertOk()
            ->assertJsonPath('streams.0.id', $streamId)
            ->assertJsonPath('streams.0.channel.username_slug', 'dj-live');

        $this->getJson('/api/live/dj-live')
            ->assertOk()
            ->assertJsonPath('channel.username_slug', 'dj-live')
            ->assertJsonPath('channel.active_stream.id', $streamId);
    }

    public function test_public_audience_token_can_be_generated_for_active_stream(): void
    {
        $user = $this->djUser('dj_plus');

        $streamId = $this->actingAs($user)
            ->postJson('/api/live/start')
            ->json('stream.id');

        $response = $this->postJson('/api/live/token', [
            'role' => 'audience',
            'live_stream_id' => $streamId,
        ])
            ->assertOk()
            ->assertJsonPath('role', 'audience')
            ->assertJsonPath('appId', '1234567890abcdef1234567890abcdef');

        $this->assertStringStartsWith('007', $response->json('token'));
        $this->assertStringNotContainsString('abcdef1234567890abcdef1234567890', $response->getContent());
    }

    public function test_live_viewer_heartbeat_lists_active_viewers_and_prunes_stale_presence(): void
    {
        $owner = $this->djUser('dj_plus');
        $streamId = $this->actingAs($owner)
            ->postJson('/api/live/start')
            ->json('stream.id');

        LiveStreamViewer::query()->create([
            'live_stream_id' => $streamId,
            'viewer_hash' => str_repeat('a', 64),
            'display_name' => 'Stale Viewer',
            'last_seen_at' => now()->subMinute(),
        ]);

        $viewer = User::factory()->create(['name' => 'Current Viewer']);
        $viewerId = '123e4567-e89b-12d3-a456-426614174000';

        $this->actingAs($viewer)
            ->postJson("/api/live/{$streamId}/viewers", ['viewer_id' => $viewerId])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('viewers.0.name', 'Current Viewer')
            ->assertJsonPath('viewers.0.is_guest', false);

        $this->assertDatabaseMissing('live_stream_viewers', [
            'display_name' => 'Stale Viewer',
        ]);

        $this->deleteJson("/api/live/{$streamId}/viewers", ['viewer_id' => $viewerId])
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_host_token_requires_stream_owner(): void
    {
        $owner = $this->djUser('dj_plus');
        $otherUser = $this->djUser('dj_plus', 'other-dj', 'Other DJ');

        $streamId = $this->actingAs($owner)
            ->postJson('/api/live/start')
            ->json('stream.id');

        $this->app['auth']->guard('web')->logout();

        $this->postJson('/api/live/token', [
            'role' => 'host',
            'live_stream_id' => $streamId,
        ])->assertUnauthorized();

        $this->actingAs($otherUser)
            ->postJson('/api/live/token', [
                'role' => 'host',
                'live_stream_id' => $streamId,
            ])->assertForbidden();

        $this->actingAs($owner)
            ->postJson('/api/live/token', [
                'role' => 'host',
                'live_stream_id' => $streamId,
            ])
            ->assertOk()
            ->assertJsonPath('role', 'host');
    }

    public function test_dj_can_end_active_stream(): void
    {
        $user = $this->djUser('dj_plus');

        $streamId = $this->actingAs($user)
            ->postJson('/api/live/start')
            ->json('stream.id');

        $this->actingAs($user)
            ->postJson('/api/live/end')
            ->assertOk()
            ->assertJsonPath('ended', true)
            ->assertJsonPath('stream.id', $streamId)
            ->assertJsonPath('stream.status', LiveStream::STATUS_ENDED);

        $this->assertDatabaseHas('live_streams', [
            'id' => $streamId,
            'status' => LiveStream::STATUS_ENDED,
        ]);

        $this->assertNotNull(LiveStream::query()->find($streamId)->ended_at);
    }

    public function test_expired_live_streams_are_ended_by_artisan_command(): void
    {
        $user = $this->djUser('dj_plus');
        $channel = $this->liveChannelFor($user);
        $expired = LiveStream::query()->create([
            'live_channel_id' => $channel->id,
            'user_id' => $user->id,
            'agora_channel_name' => 'expired-stream',
            'title' => 'Expired',
            'status' => LiveStream::STATUS_LIVE,
            'max_duration_minutes' => 30,
            'started_at' => now()->subMinutes(31),
        ]);
        $active = LiveStream::query()->create([
            'live_channel_id' => $channel->id,
            'user_id' => $user->id,
            'agora_channel_name' => 'active-stream',
            'title' => 'Active',
            'status' => LiveStream::STATUS_LIVE,
            'max_duration_minutes' => 30,
            'started_at' => now()->subMinutes(10),
        ]);
        $unlimited = LiveStream::query()->create([
            'live_channel_id' => $channel->id,
            'user_id' => $user->id,
            'agora_channel_name' => 'unlimited-stream',
            'title' => 'Unlimited',
            'status' => LiveStream::STATUS_LIVE,
            'max_duration_minutes' => null,
            'started_at' => now()->subHours(4),
        ]);

        $this->artisan('live:end-expired')
            ->expectsOutput('Ended 1 expired live stream.')
            ->assertExitCode(0);

        $this->assertSame(LiveStream::STATUS_ENDED, $expired->refresh()->status);
        $this->assertNotNull($expired->ended_at);
        $this->assertSame(LiveStream::STATUS_LIVE, $active->refresh()->status);
        $this->assertSame(LiveStream::STATUS_LIVE, $unlimited->refresh()->status);
    }

    private function djUser(string $tier, string $handle = 'dj-live', string $djName = 'DJ Live'): User
    {
        $user = User::factory()->create([
            'name' => $djName,
            'media_storage_tier' => $tier,
        ]);

        DjProfile::query()->create([
            'user_id' => $user->id,
            'dj_name' => $djName,
            'handle' => $handle,
            'profile_status' => 'active',
            'visibility' => 'public',
            'bio' => 'Live test profile.',
        ]);

        return $user;
    }

    private function liveChannelFor(User $user): LiveChannel
    {
        return LiveChannel::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'username_slug' => $user->djProfile->handle,
                'title' => $user->djProfile->dj_name,
                'description' => $user->djProfile->bio,
                'is_enabled' => true,
            ],
        );
    }

    private function createMonthlyStreams(User $user, int $count): void
    {
        $channel = $this->liveChannelFor($user);

        foreach (range(1, $count) as $number) {
            LiveStream::query()->create([
                'live_channel_id' => $channel->id,
                'user_id' => $user->id,
                'agora_channel_name' => 'monthly-'.$user->id.'-'.$number,
                'title' => 'Monthly Stream '.$number,
                'status' => LiveStream::STATUS_ENDED,
                'max_duration_minutes' => 30,
                'started_at' => now()->startOfMonth()->addDays(($number - 1) % 20),
                'ended_at' => now()->startOfMonth()->addDays(($number - 1) % 20)->addMinutes(20),
            ]);
        }
    }
}
