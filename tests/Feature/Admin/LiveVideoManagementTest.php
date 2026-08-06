<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\LiveChannel;
use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiveVideoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_monitor_available_and_missing_live_recordings(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        [$available, $missing] = $this->savedStreams();
        Storage::disk('public')->put($available->recording_storage_path, 'recording contents');

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/livevideos')
            ->assertOk()
            ->assertSee('Live Stream Monitor')
            ->assertSee($available->title)
            ->assertSee($missing->title)
            ->assertSee('File Available')
            ->assertSee('File Missing');
    }

    public function test_admin_removal_deletes_the_recording_file_and_saved_stream(): void
    {
        Storage::fake('public');
        [$stream] = $this->savedStreams();
        Storage::disk('public')->put($stream->recording_storage_path, 'recording contents');

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.admincenter.live-videos.destroy', $stream))
            ->assertRedirect(route('admin.admincenter.live-videos.index'))
            ->assertSessionHas('status');

        Storage::disk('public')->assertMissing($stream->recording_storage_path);
        $this->assertDatabaseMissing('live_streams', ['id' => $stream->id]);
    }

    public function test_admin_cannot_remove_an_active_live_stream(): void
    {
        [$stream] = $this->savedStreams();
        $stream->update(['status' => LiveStream::STATUS_LIVE, 'ended_at' => null]);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.admincenter.live-videos.destroy', $stream))
            ->assertConflict();

        $this->assertDatabaseHas('live_streams', ['id' => $stream->id]);
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Live Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
    }

    private function savedStreams(): array
    {
        $user = User::factory()->create(['name' => 'Test DJ']);
        $channel = LiveChannel::query()->create([
            'user_id' => $user->id,
            'username_slug' => 'test-dj',
            'title' => 'Test DJ',
            'is_enabled' => true,
        ]);

        return collect(['Available Recording', 'Missing Recording'])->map(function (string $title, int $index) use ($channel, $user): LiveStream {
            return LiveStream::query()->create([
                'live_channel_id' => $channel->id,
                'user_id' => $user->id,
                'agora_channel_name' => 'admin-video-'.($index + 1),
                'title' => $title,
                'status' => LiveStream::STATUS_ENDED,
                'recording_enabled' => true,
                'recording_status' => 'ready',
                'recording_storage_path' => 'live-replays/'.($index + 1).'/replay.webm',
                'started_at' => now()->subHour(),
                'ended_at' => now(),
            ]);
        })->all();
    }
}
