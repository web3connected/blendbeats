<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\DjBattle;
use App\Models\DjGenre;
use App\Models\DjProfile;
use App\Models\Follower;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserGamificationStat;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BattleDemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            GamificationActionSeeder::class,
            BadgeSeeder::class,
        ]);

        $wallets = app(WalletService::class);
        $djs = collect($this->djFixtures())
            ->map(fn (array $fixture): DjProfile => $this->seedDj($fixture, $wallets))
            ->values();

        $fans = collect($this->fanFixtures())
            ->map(fn (array $fixture): User => $this->seedFan($fixture, $wallets))
            ->values();

        $this->seedFollowers($fans->all(), $djs->all());
        $this->seedDemoBattles($djs->all());
        $this->call(BattleTestingWalletSeeder::class);
    }

    private function seedDj(array $fixture, WalletService $wallets): DjProfile
    {
        $user = User::query()->updateOrCreate(
            ['email' => $fixture['email']],
            [
                'name' => $fixture['name'],
                'first_name' => Str::before($fixture['name'], ' '),
                'last_name' => Str::after($fixture['name'], ' ') ?: null,
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'use_gravatar' => true,
                'is_gravatar' => true,
                'media_storage_tier' => $fixture['tier'],
            ],
        );

        $profile = DjProfile::query()->updateOrCreate(
            ['handle' => $fixture['handle']],
            [
                'user_id' => $user->id,
                'dj_name' => $fixture['dj_name'],
                'profile_headline' => $fixture['headline'],
                'bio' => $fixture['bio'],
                'dj_type' => $fixture['dj_type'],
                'city' => $fixture['city'],
                'state' => $fixture['state'],
                'country' => $fixture['country'],
                'booking_enabled' => $fixture['booking_enabled'],
                'battle_enabled' => $fixture['battle_enabled'],
                'profile_status' => 'active',
                'visibility' => 'public',
                'verification_status' => $fixture['verified'] ? 'verified' : 'unverified',
                'published_at' => now()->subDays($fixture['published_days_ago']),
                'view_count' => $fixture['view_count'],
            ],
        );

        $this->syncGenre($profile, $fixture['genre']);
        $this->seedStats($user, $fixture['dj_xp'], 0, $fixture['dj_level'], 1, $fixture['rank'], 'Listener');
        $this->seedBadges($user, $fixture['badges']);
        $this->seedDemoWalletCredit($user, $wallets, $fixture['tokens']);

        return $profile->refresh();
    }

    private function seedFan(array $fixture, WalletService $wallets): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $fixture['email']],
            [
                'name' => $fixture['name'],
                'first_name' => Str::before($fixture['name'], ' '),
                'last_name' => Str::after($fixture['name'], ' ') ?: null,
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'use_gravatar' => true,
                'is_gravatar' => true,
                'media_storage_tier' => config('billing.subscription.free_tier', 'free'),
            ],
        );

        $this->seedStats($user, 0, $fixture['fan_xp'], 1, $fixture['fan_level'], 'New DJ', $fixture['rank']);
        $this->seedBadges($user, $fixture['badges']);
        $this->seedDemoWalletCredit($user, $wallets, $fixture['tokens']);

        return $user->refresh();
    }

    private function syncGenre(DjProfile $profile, string $genreName): void
    {
        $genre = DjGenre::query()->firstOrCreate(
            ['slug' => Str::slug($genreName)],
            ['name' => $genreName, 'is_active' => true],
        );

        $profile->genres()->syncWithoutDetaching([
            $genre->id => [
                'is_primary' => true,
                'sort_order' => 0,
            ],
        ]);
    }

    private function seedStats(
        User $user,
        int $djXp,
        int $fanXp,
        int $djLevel,
        int $fanLevel,
        string $djRank,
        string $fanRank,
    ): void {
        UserGamificationStat::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'dj_xp' => $djXp,
                'fan_xp' => $fanXp,
                'total_xp' => $djXp + $fanXp,
                'dj_level' => $djLevel,
                'fan_level' => $fanLevel,
                'total_level' => max($djLevel, $fanLevel),
                'dj_rank' => $djRank,
                'fan_rank' => $fanRank,
                'last_activity_at' => now()->subDays(rand(0, 10)),
            ],
        );
    }

    private function seedBadges(User $user, array $badgeKeys): void
    {
        foreach ($badgeKeys as $badgeKey) {
            $badge = Badge::query()->where('badge_key', $badgeKey)->first();

            if (! $badge) {
                continue;
            }

            UserBadge::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                ],
                [
                    'unlocked_at' => now()->subDays(rand(1, 30)),
                    'metadata' => ['source' => 'battle_demo_seeder'],
                ],
            );
        }
    }

    private function seedDemoWalletCredit(User $user, WalletService $wallets, int $tokens): void
    {
        $wallet = $wallets->walletFor($user);

        if ($wallet->transactions()
            ->where('type', WalletService::TYPE_BETA_GRANT)
            ->where('description', 'Demo testing token balance.')
            ->exists()) {
            return;
        }

        $wallets->credit($user, $tokens, WalletService::TYPE_BETA_GRANT, [
            'description' => 'Demo testing token balance.',
            'metadata' => [
                'source' => 'battle_demo_seeder',
            ],
        ]);
    }

    private function seedFollowers(array $fans, array $djs): void
    {
        foreach ($fans as $fanIndex => $fan) {
            foreach ($djs as $djIndex => $profile) {
                if (($fanIndex + $djIndex) % 3 === 0 || $djIndex < 2) {
                    Follower::query()->firstOrCreate([
                        'follower_user_id' => $fan->id,
                        'followed_dj_id' => $profile->id,
                    ]);
                }
            }
        }
    }

    private function seedDemoBattles(array $djs): void
    {
        $battlePairs = [
            ['Demo Battle: Neon vs Cipher', 'dj-neon-lux', 'dj-cipher-7', 'dj-neon-lux'],
            ['Demo Battle: Velvet vs Nova', 'dj-velvet-echo', 'dj-nova-kick', 'dj-nova-kick'],
            ['Demo Battle: Atlas vs Pulse', 'dj-atlas-scratch', 'dj-pulse-mode', 'dj-atlas-scratch'],
            ['Demo Battle: Neon vs Nova', 'dj-neon-lux', 'dj-nova-kick', 'dj-neon-lux'],
            ['Demo Battle: Cipher vs Velvet', 'dj-cipher-7', 'dj-velvet-echo', 'dj-cipher-7'],
            ['Demo Battle: Pulse vs Orbit', 'dj-pulse-mode', 'dj-orbit-fader', 'dj-orbit-fader'],
        ];

        $profilesByHandle = collect($djs)->keyBy('handle');

        foreach ($battlePairs as $index => [$title, $challengerHandle, $opponentHandle, $winnerHandle]) {
            $challenger = $profilesByHandle->get($challengerHandle);
            $opponent = $profilesByHandle->get($opponentHandle);
            $winner = $profilesByHandle->get($winnerHandle);

            if (! $challenger || ! $opponent || ! $winner) {
                continue;
            }

            DjBattle::query()->updateOrCreate(
                ['title' => $title],
                [
                    'challenger_dj_profile_id' => $challenger->id,
                    'opponent_dj_profile_id' => $opponent->id,
                    'created_by_user_id' => $challenger->user_id,
                    'battle_type' => Arr::random(['mix', 'scratch', 'open_format', 'theme']),
                    'status' => 'completed',
                    'theme' => Arr::random(['Clean transitions', '90s club heat', 'Scratch control', 'Peak-hour blends']),
                    'description' => 'Demo completed battle for testing the Battle Hub.',
                    'rules' => 'Three-minute routine. One take.',
                    'duration_seconds' => 180,
                    'minimum_votes' => 1,
                    'stake_amount' => 0,
                    'currency' => 'TOKENS',
                    'winner_dj_profile_id' => $winner->id,
                    'accepted_at' => now()->subDays(20 - $index),
                    'recording_started_at' => now()->subDays(19 - $index),
                    'voting_started_at' => now()->subDays(18 - $index),
                    'voting_ends_at' => now()->subDays(17 - $index),
                    'completed_at' => now()->subDays(16 - $index),
                ],
            );
        }
    }

    private function djFixtures(): array
    {
        return [
            [
                'name' => 'Neon Lux',
                'email' => 'dj.neon@example.com',
                'dj_name' => 'DJ Neon Lux',
                'handle' => 'dj-neon-lux',
                'headline' => 'Open format blends with clean battle timing.',
                'bio' => 'Demo battle-ready DJ profile.',
                'dj_type' => 'open_format',
                'genre' => 'Hip-Hop',
                'city' => 'Atlanta',
                'state' => 'GA',
                'country' => 'US',
                'tier' => 'pro',
                'booking_enabled' => true,
                'battle_enabled' => true,
                'verified' => true,
                'published_days_ago' => 35,
                'view_count' => 4200,
                'dj_xp' => 2600,
                'dj_level' => 6,
                'rank' => 'Headliner',
                'tokens' => 700,
                'badges' => ['first_portfolio_upload', 'fan_favorite', 'weekly_grinder'],
            ],
            [
                'name' => 'Cipher Seven',
                'email' => 'dj.cipher@example.com',
                'dj_name' => 'DJ Cipher 7',
                'handle' => 'dj-cipher-7',
                'headline' => 'Scratch-heavy sets and sharp routines.',
                'bio' => 'Demo battle-ready DJ profile.',
                'dj_type' => 'turntablist',
                'genre' => 'Battle',
                'city' => 'New York',
                'state' => 'NY',
                'country' => 'US',
                'tier' => 'pro',
                'booking_enabled' => true,
                'battle_enabled' => true,
                'verified' => true,
                'published_days_ago' => 41,
                'view_count' => 3900,
                'dj_xp' => 3100,
                'dj_level' => 7,
                'rank' => 'Legend',
                'tokens' => 650,
                'badges' => ['first_scratch_upload', 'fan_favorite'],
            ],
            [
                'name' => 'Velvet Echo',
                'email' => 'dj.velvet@example.com',
                'dj_name' => 'Velvet Echo',
                'handle' => 'dj-velvet-echo',
                'headline' => 'House and club sets built for crowd control.',
                'bio' => 'Demo battle-ready DJ profile.',
                'dj_type' => 'club_dj',
                'genre' => 'House',
                'city' => 'Chicago',
                'state' => 'IL',
                'country' => 'US',
                'tier' => 'plus',
                'booking_enabled' => true,
                'battle_enabled' => true,
                'verified' => false,
                'published_days_ago' => 18,
                'view_count' => 2100,
                'dj_xp' => 1200,
                'dj_level' => 5,
                'rank' => 'Battle DJ',
                'tokens' => 520,
                'badges' => ['first_portfolio_upload'],
            ],
            [
                'name' => 'Nova Kick',
                'email' => 'dj.nova@example.com',
                'dj_name' => 'Nova Kick',
                'handle' => 'dj-nova-kick',
                'headline' => 'Fast cuts, bass pressure, and festival energy.',
                'bio' => 'Demo battle-ready DJ profile.',
                'dj_type' => 'producer_dj',
                'genre' => 'EDM',
                'city' => 'Miami',
                'state' => 'FL',
                'country' => 'US',
                'tier' => 'plus',
                'booking_enabled' => false,
                'battle_enabled' => true,
                'verified' => false,
                'published_days_ago' => 12,
                'view_count' => 1850,
                'dj_xp' => 900,
                'dj_level' => 4,
                'rank' => 'Club DJ',
                'tokens' => 460,
                'badges' => ['weekly_grinder'],
            ],
            [
                'name' => 'Atlas Scratch',
                'email' => 'dj.atlas@example.com',
                'dj_name' => 'Atlas Scratch',
                'handle' => 'dj-atlas-scratch',
                'headline' => 'Technical scratch routines with old-school roots.',
                'bio' => 'Demo battle-ready DJ profile.',
                'dj_type' => 'turntablist',
                'genre' => 'Hip-Hop',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'country' => 'US',
                'tier' => 'free',
                'booking_enabled' => false,
                'battle_enabled' => true,
                'verified' => true,
                'published_days_ago' => 27,
                'view_count' => 2600,
                'dj_xp' => 1500,
                'dj_level' => 5,
                'rank' => 'Battle DJ',
                'tokens' => 400,
                'badges' => ['first_scratch_upload'],
            ],
            [
                'name' => 'Pulse Mode',
                'email' => 'dj.pulse@example.com',
                'dj_name' => 'Pulse Mode',
                'handle' => 'dj-pulse-mode',
                'headline' => 'Radio-ready open format with battle instincts.',
                'bio' => 'Demo battle-ready DJ profile.',
                'dj_type' => 'radio_dj',
                'genre' => 'Open Format',
                'city' => 'Detroit',
                'state' => 'MI',
                'country' => 'US',
                'tier' => 'free',
                'booking_enabled' => true,
                'battle_enabled' => true,
                'verified' => false,
                'published_days_ago' => 7,
                'view_count' => 980,
                'dj_xp' => 460,
                'dj_level' => 3,
                'rank' => 'Local DJ',
                'tokens' => 360,
                'badges' => [],
            ],
            [
                'name' => 'Orbit Fader',
                'email' => 'dj.orbit@example.com',
                'dj_name' => 'Orbit Fader',
                'handle' => 'dj-orbit-fader',
                'headline' => 'Smooth transitions and melodic pressure.',
                'bio' => 'Demo DJ profile.',
                'dj_type' => 'open_format',
                'genre' => 'R&B',
                'city' => 'Toronto',
                'state' => 'ON',
                'country' => 'CA',
                'tier' => 'free',
                'booking_enabled' => true,
                'battle_enabled' => true,
                'verified' => false,
                'published_days_ago' => 22,
                'view_count' => 1400,
                'dj_xp' => 700,
                'dj_level' => 4,
                'rank' => 'Club DJ',
                'tokens' => 420,
                'badges' => ['first_portfolio_upload'],
            ],
            [
                'name' => 'Static Bloom',
                'email' => 'dj.static@example.com',
                'dj_name' => 'Static Bloom',
                'handle' => 'dj-static-bloom',
                'headline' => 'Experimental selections and deep crates.',
                'bio' => 'Demo DJ profile.',
                'dj_type' => 'producer_dj',
                'genre' => 'Electronic',
                'city' => 'London',
                'state' => null,
                'country' => 'UK',
                'tier' => 'free',
                'booking_enabled' => false,
                'battle_enabled' => false,
                'verified' => false,
                'published_days_ago' => 3,
                'view_count' => 320,
                'dj_xp' => 80,
                'dj_level' => 1,
                'rank' => 'New DJ',
                'tokens' => 250,
                'badges' => [],
            ],
        ];
    }

    private function fanFixtures(): array
    {
        return [
            ['name' => 'Maya Rhodes', 'email' => 'fan.maya@example.com', 'fan_xp' => 380, 'fan_level' => 3, 'rank' => 'Super Fan', 'tokens' => 220, 'badges' => ['battle_voter']],
            ['name' => 'Andre King', 'email' => 'fan.andre@example.com', 'fan_xp' => 820, 'fan_level' => 4, 'rank' => 'Taste Maker', 'tokens' => 240, 'badges' => ['battle_voter', 'super_fan']],
            ['name' => 'Selena Cruz', 'email' => 'fan.selena@example.com', 'fan_xp' => 120, 'fan_level' => 2, 'rank' => 'Supporter', 'tokens' => 180, 'badges' => ['battle_voter']],
            ['name' => 'Jules Carter', 'email' => 'fan.jules@example.com', 'fan_xp' => 40, 'fan_level' => 1, 'rank' => 'Listener', 'tokens' => 150, 'badges' => []],
            ['name' => 'Nia Brooks', 'email' => 'fan.nia@example.com', 'fan_xp' => 1150, 'fan_level' => 5, 'rank' => 'Crowd Captain', 'tokens' => 300, 'badges' => ['battle_voter', 'super_fan', 'weekly_grinder']],
            ['name' => 'Leo Grant', 'email' => 'fan.leo@example.com', 'fan_xp' => 260, 'fan_level' => 3, 'rank' => 'Super Fan', 'tokens' => 190, 'badges' => ['battle_voter']],
        ];
    }
}
