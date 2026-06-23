<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\GamificationEvent;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserGamificationStat;
use App\Services\GamificationService;
use Database\Seeders\BadgeSeeder;
use Database\Seeders\GamificationActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GamificationActionSeeder::class);
    }

    public function test_awarding_portfolio_uploaded_creates_gamification_event(): void
    {
        $user = User::factory()->create();

        $awarded = app(GamificationService::class)->award(
            $user->id,
            'portfolio_uploaded',
            'media_file',
            42,
        );

        $this->assertTrue($awarded);
        $this->assertDatabaseHas('gamification_events', [
            'user_id' => $user->id,
            'action_key' => 'portfolio_uploaded',
            'role_context' => 'dj',
            'xp_awarded' => 25,
            'target_type' => 'media_file',
            'target_id' => 42,
        ]);
    }

    public function test_awarding_portfolio_uploaded_creates_and_updates_user_stats(): void
    {
        $user = User::factory()->create();

        app(GamificationService::class)->award(
            $user->id,
            'portfolio_uploaded',
            'media_file',
            42,
        );

        $stats = UserGamificationStat::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(25, (int) $stats->dj_xp);
        $this->assertSame(0, (int) $stats->fan_xp);
        $this->assertSame(25, (int) $stats->total_xp);
        $this->assertSame(1, (int) $stats->dj_level);
        $this->assertSame(1, (int) $stats->fan_level);
        $this->assertSame(1, (int) $stats->total_level);
        $this->assertSame('New DJ', $stats->dj_rank);
        $this->assertSame('Listener', $stats->fan_rank);
        $this->assertNotNull($stats->last_activity_at);
    }

    public function test_duplicate_same_user_action_and_target_does_not_award_twice(): void
    {
        $user = User::factory()->create();
        $gamification = app(GamificationService::class);

        $firstAward = $gamification->award($user->id, 'portfolio_uploaded', 'media_file', 42);
        $secondAward = $gamification->award($user->id, 'portfolio_uploaded', 'media_file', 42);

        $this->assertTrue($firstAward);
        $this->assertFalse($secondAward);
        $this->assertSame(1, GamificationEvent::query()->where('user_id', $user->id)->count());

        $stats = UserGamificationStat::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(25, (int) $stats->dj_xp);
        $this->assertSame(25, (int) $stats->total_xp);
    }

    public function test_portfolio_uploaded_unlocks_first_portfolio_upload_badge_once(): void
    {
        $this->seed(BadgeSeeder::class);

        $user = User::factory()->create();
        $gamification = app(GamificationService::class);

        $firstAward = $gamification->award($user->id, 'portfolio_uploaded', 'media_file', 42);
        $secondAward = $gamification->award($user->id, 'portfolio_uploaded', 'media_file', 43);

        $badge = Badge::query()
            ->where('badge_key', 'first_portfolio_upload')
            ->firstOrFail();

        $this->assertTrue($firstAward);
        $this->assertTrue($secondAward);
        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
        ]);

        $userBadge = UserBadge::query()
            ->where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->firstOrFail();

        $this->assertSame('portfolio_uploaded', $userBadge->metadata['action_key']);
        $this->assertSame(1, $userBadge->metadata['event_count']);
        $this->assertSame(1, UserBadge::query()
            ->where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->count());
    }

    public function test_daily_login_awards_fan_xp_once_per_day(): void
    {
        $user = User::factory()->create();
        $gamification = app(GamificationService::class);

        $firstAward = $gamification->awardDailyLogin($user->id);
        $secondAward = $gamification->awardDailyLogin($user->id);

        $this->assertTrue($firstAward);
        $this->assertFalse($secondAward);

        $this->assertDatabaseHas('gamification_events', [
            'user_id' => $user->id,
            'action_key' => 'daily_login',
            'role_context' => 'fan',
            'xp_awarded' => 10,
            'target_type' => 'daily_login',
            'target_id' => (int) now()->format('Ymd'),
        ]);

        $this->assertSame(1, GamificationEvent::query()
            ->where('user_id', $user->id)
            ->where('action_key', 'daily_login')
            ->count());

        $stats = UserGamificationStat::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(0, (int) $stats->dj_xp);
        $this->assertSame(10, (int) $stats->fan_xp);
        $this->assertSame(10, (int) $stats->total_xp);
        $this->assertSame(1, (int) $stats->fan_level);
        $this->assertSame('New DJ', $stats->dj_rank);
        $this->assertSame('Listener', $stats->fan_rank);
        $this->assertNotNull($stats->last_activity_at);
    }

    public function test_missing_action_key_returns_false(): void
    {
        $user = User::factory()->create();

        $awarded = app(GamificationService::class)->award(
            $user->id,
            'missing_action_key',
            'media_file',
            42,
        );

        $this->assertFalse($awarded);
        $this->assertDatabaseCount('gamification_events', 0);
        $this->assertDatabaseCount('user_gamification_stats', 0);
    }

    public function test_level_increases_when_xp_threshold_is_reached(): void
    {
        $user = User::factory()->create();
        $gamification = app(GamificationService::class);

        for ($index = 1; $index <= 4; $index++) {
            $gamification->award($user->id, 'portfolio_uploaded', 'media_file', $index);
        }

        $stats = UserGamificationStat::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(100, (int) $stats->dj_xp);
        $this->assertSame(2, (int) $stats->dj_level);
        $this->assertSame(2, (int) $stats->total_level);
        $this->assertSame('Bedroom DJ', $stats->dj_rank);
        $this->assertSame('Listener', $stats->fan_rank);
    }

    public function test_fan_rank_increases_when_fan_level_threshold_is_reached(): void
    {
        $user = User::factory()->create();
        $gamification = app(GamificationService::class);

        for ($index = 1; $index <= 10; $index++) {
            $gamification->award($user->id, 'daily_login', 'daily_login', 20260600 + $index);
        }

        $stats = UserGamificationStat::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(100, (int) $stats->fan_xp);
        $this->assertSame(2, (int) $stats->fan_level);
        $this->assertSame(2, (int) $stats->total_level);
        $this->assertSame('New DJ', $stats->dj_rank);
        $this->assertSame('Supporter', $stats->fan_rank);
    }
}
