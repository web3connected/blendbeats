<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\SiteActivityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_requests_are_tracked_as_site_activity_events(): void
    {
        $user = User::factory()->create([
            'name' => 'Activity Tester',
            'email' => 'activity@example.com',
        ]);

        $this->actingAs($user)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile',
                'Referer' => 'https://example.com/campaign',
            ])
            ->get('/')
            ->assertOk();

        $this->assertDatabaseHas('site_activity_events', [
            'user_id' => $user->id,
            'admin_id' => null,
            'method' => 'GET',
            'path' => '/',
            'route_name' => 'home',
            'status_code' => 200,
            'referrer_host' => 'example.com',
            'device_type' => 'mobile',
            'is_bot' => false,
        ]);
    }

    public function test_admin_can_view_site_analytics_dashboard(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Analytics Admin',
            'email' => 'analytics-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Tracked User',
            'email' => 'tracked@example.com',
        ]);

        SiteActivityEvent::query()->create([
            'occurred_at' => now()->subHour(),
            'user_id' => $user->id,
            'visitor_key' => hash('sha256', 'visitor-one'),
            'session_id_hash' => hash('sha256', 'session-one'),
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'method' => 'GET',
            'path' => '/battles',
            'route_name' => 'battles.index',
            'status_code' => 200,
            'duration_ms' => 42,
            'referrer_host' => 'google.com',
            'referrer_url' => 'https://google.com/search?q=blendbeats',
            'user_agent' => 'Mozilla/5.0',
            'device_type' => 'desktop',
            'is_bot' => false,
            'is_ajax' => false,
        ]);

        SiteActivityEvent::query()->create([
            'occurred_at' => now()->subMinutes(20),
            'admin_id' => $admin->id,
            'visitor_key' => hash('sha256', 'visitor-admin'),
            'session_id_hash' => hash('sha256', 'session-admin'),
            'ip_hash' => hash('sha256', '127.0.0.2'),
            'method' => 'POST',
            'path' => '/admin/admincenter/beta-tokens/1/grant',
            'route_name' => 'admin.admincenter.beta-tokens.grant',
            'status_code' => 302,
            'duration_ms' => 88,
            'device_type' => 'desktop',
            'is_bot' => false,
            'is_ajax' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/siteanalytics')
            ->assertOk()
            ->assertSee('Site Analytics')
            ->assertSee('Tracked Events')
            ->assertSee('/battles')
            ->assertSee('Tracked User')
            ->assertSee('Analytics Admin')
            ->assertSee('google.com');
    }
}
