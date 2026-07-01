<?php

namespace Tests\Feature;

use App\Models\DjBattle;
use App\Models\DjProfile;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DjBattleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_challenge_requires_authentication(): void
    {
        $opponent = $this->battleReadyProfile(User::factory()->create());

        $this->postJson('/api/battles', [
            'opponent_dj_profile_id' => $opponent->id,
            'battle_type' => 'open_format',
            'title' => 'Open Format Test',
        ])->assertUnauthorized();
    }

    public function test_battle_challenge_creates_pending_invitation_and_notifies_opponent(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $challengerProfile = $this->battleReadyProfile($challenger, ['dj_name' => 'DJ Alpha']);
        $opponentProfile = $this->battleReadyProfile($opponent, ['dj_name' => 'DJ Beta']);
        $wallets = app(WalletService::class);

        $wallets->credit($challenger, 100, 'token_purchase');

        $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'Kitchen Heat',
                'theme' => 'Warmup set',
                'voting_duration_hours' => 48,
                'stake_amount' => 25,
                'challenge_message' => 'I have been waiting to battle you.',
            ])
            ->assertCreated()
            ->assertJsonPath('battle.status', 'pending')
            ->assertJsonPath('battle.stake_amount', 25)
            ->assertJsonPath('battle.voting_duration_hours', 48)
            ->assertJsonPath('battle.challenge_message', 'I have been waiting to battle you.')
            ->assertJsonPath('battle.challenger.id', $challengerProfile->id)
            ->assertJsonPath('battle.opponent.id', $opponentProfile->id);

        $wallet = $challenger->wallet()->firstOrFail();
        $this->assertSame(100, $wallet->available_balance);
        $this->assertSame(0, $wallet->locked_balance);

        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $challenger->id,
            'type' => WalletService::TYPE_BATTLE_STAKE_LOCKED,
        ]);
        $this->assertDatabaseHas('dj_battle_events', [
            'actor_user_id' => $challenger->id,
            'event_type' => 'challenge_created',
            'to_status' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $opponent->id,
            'notifiable_type' => $opponent->getMorphClass(),
        ]);

        $this->getJson('/api/battles')
            ->assertOk()
            ->assertJsonCount(0, 'battles');
    }

    public function test_opponent_acceptance_opens_ready_phase_without_locking_stakes(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger, ['dj_name' => 'DJ Alpha']);
        $opponentProfile = $this->battleReadyProfile($opponent, ['dj_name' => 'DJ Beta']);
        $wallets = app(WalletService::class);

        $wallets->credit($challenger, 100, 'token_purchase');
        $wallets->credit($opponent, 100, 'token_purchase');

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'scratch',
                'title' => 'Scratch Session',
                'stake_amount' => 30,
            ])
            ->assertCreated()
            ->json('battle.uuid');

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/accept")
            ->assertOk()
            ->assertJsonPath('battle.status', 'accepted')
            ->assertJsonPath('battle.readiness.challenger_ready', false)
            ->assertJsonPath('battle.readiness.opponent_ready', false)
            ->assertJsonCount(2, 'battle.entries');

        $this->assertDatabaseHas('dj_battles', [
            'uuid' => $battleUuid,
            'status' => DjBattle::STATUS_ACCEPTED,
            'stake_amount' => 30,
        ]);
        $this->assertDatabaseHas('dj_battle_events', [
            'actor_user_id' => $opponent->id,
            'event_type' => 'challenge_accepted',
            'from_status' => 'pending',
            'to_status' => 'accepted',
        ]);
        $this->assertDatabaseCount('dj_battle_entries', 2);

        $challengerWallet = $challenger->wallet()->firstOrFail();
        $opponentWallet = $opponent->wallet()->firstOrFail();

        $this->assertSame(100, $challengerWallet->available_balance);
        $this->assertSame(0, $challengerWallet->locked_balance);
        $this->assertSame(100, $opponentWallet->available_balance);
        $this->assertSame(0, $opponentWallet->locked_balance);

        $this->getJson('/api/battles')
            ->assertOk()
            ->assertJsonCount(1, 'battles')
            ->assertJsonPath('battles.0.uuid', $battleUuid);
    }

    public function test_declining_battle_closes_invitation_without_wallet_refund(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger);
        $opponentProfile = $this->battleReadyProfile($opponent);
        $wallets = app(WalletService::class);

        $wallets->credit($challenger, 50, 'token_purchase');

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'mix',
                'title' => 'Blend Off',
                'stake_amount' => 20,
            ])
            ->assertCreated()
            ->json('battle.uuid');

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/decline")
            ->assertOk()
            ->assertJsonPath('battle.status', 'declined');

        $wallet = $challenger->wallet()->firstOrFail();
        $this->assertSame(50, $wallet->available_balance);
        $this->assertSame(0, $wallet->locked_balance);

        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $challenger->id,
            'type' => WalletService::TYPE_BATTLE_REFUND,
        ]);
    }

    public function test_challenge_rejects_non_battle_ready_opponent(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger);
        $opponentProfile = $this->battleReadyProfile($opponent, ['battle_enabled' => false]);

        $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'Not Ready Yet',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('opponent_dj_profile_id');

        $this->assertDatabaseCount('dj_battles', 0);
    }

    public function test_challenger_can_send_invitation_before_their_profile_is_battle_ready(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger, ['battle_enabled' => false]);
        $opponentProfile = $this->battleReadyProfile($opponent);
        $wallets = app(WalletService::class);

        $wallets->credit($challenger, 100, 'token_purchase');
        $wallets->credit($opponent, 100, 'token_purchase');

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'Warmup Challenge',
                'stake_amount' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('battle.status', DjBattle::STATUS_PENDING)
            ->json('battle.uuid');

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/accept")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_ACCEPTED);

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/ready")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dj_profile');
    }

    public function test_ready_requires_sufficient_wallet_balance(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger);
        $opponentProfile = $this->battleReadyProfile($opponent);
        $wallets = app(WalletService::class);

        $wallets->credit($challenger, 100, 'token_purchase');
        $wallets->credit($opponent, 5, 'token_purchase');

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'Too Rich',
                'stake_amount' => 10,
            ])
            ->assertCreated()
            ->json('battle.uuid');

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/accept")
            ->assertOk();

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/ready")
            ->assertOk()
            ->assertJsonPath('battle.status', 'accepted')
            ->assertJsonPath('battle.readiness.challenger_ready', true);

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/ready")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stake_amount');

        $this->assertDatabaseHas('dj_battles', [
            'uuid' => $battleUuid,
            'status' => DjBattle::STATUS_ACCEPTED,
        ]);

        $challengerWallet = $challenger->wallet()->firstOrFail();
        $opponentWallet = $opponent->wallet()->firstOrFail();

        $this->assertSame(100, $challengerWallet->available_balance);
        $this->assertSame(0, $challengerWallet->locked_balance);
        $this->assertSame(5, $opponentWallet->available_balance);
        $this->assertSame(0, $opponentWallet->locked_balance);
    }

    public function test_both_djs_ready_locks_stakes_and_starts_recording_window(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger);
        $opponentProfile = $this->battleReadyProfile($opponent);
        $wallets = app(WalletService::class);

        $wallets->credit($challenger, 100, 'token_purchase');
        $wallets->credit($opponent, 100, 'token_purchase');

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'Ready Check',
                'stake_amount' => 30,
            ])
            ->assertCreated()
            ->json('battle.uuid');

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/accept")
            ->assertOk();

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/ready")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_ACCEPTED)
            ->assertJsonPath('battle.readiness.challenger_ready', true)
            ->assertJsonPath('battle.readiness.opponent_ready', false);

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/ready")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_RECORDING)
            ->assertJsonPath('battle.readiness.both_ready', true)
            ->assertJsonPath('battle.sample_pack_status', DjBattle::SAMPLE_PACK_BYPASSED)
            ->assertJsonPath('battle.fan_reward_pool_amount', 6)
            ->assertJsonPath('battle.prize_pool_amount', 54);

        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'battle_started',
            'from_status' => DjBattle::STATUS_ACCEPTED,
            'to_status' => DjBattle::STATUS_RECORDING,
        ]);
        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'sample_pack_bypassed',
        ]);

        $challengerWallet = $challenger->wallet()->firstOrFail();
        $opponentWallet = $opponent->wallet()->firstOrFail();

        $this->assertSame(70, $challengerWallet->available_balance);
        $this->assertSame(30, $challengerWallet->locked_balance);
        $this->assertSame(70, $opponentWallet->available_balance);
        $this->assertSame(30, $opponentWallet->locked_balance);

        $this->assertDatabaseCount('wallet_transactions', 4);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $challenger->id,
            'type' => WalletService::TYPE_BATTLE_STAKE_LOCKED,
            'direction' => 'lock',
            'status' => 'locked',
            'amount' => 30,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $opponent->id,
            'type' => WalletService::TYPE_BATTLE_STAKE_LOCKED,
            'direction' => 'lock',
            'status' => 'locked',
            'amount' => 30,
        ]);
    }

    public function test_local_testing_can_simulate_other_dj_ready_after_current_dj_is_ready(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger);
        $opponentProfile = $this->battleReadyProfile($opponent);

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'One Browser Ready Test',
                'stake_amount' => 0,
            ])
            ->assertCreated()
            ->json('battle.uuid');

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/accept")
            ->assertOk();

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/ready")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_ACCEPTED)
            ->assertJsonPath('battle.readiness.challenger_ready', true)
            ->assertJsonPath('battle.readiness.opponent_ready', false);

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/ready/test-opponent")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_RECORDING)
            ->assertJsonPath('battle.readiness.both_ready', true);

        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'participant_ready_test_simulated',
        ]);
        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'battle_started',
            'to_status' => DjBattle::STATUS_RECORDING,
        ]);
    }

    public function test_participant_can_bypass_pending_sample_pack_for_testing(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger);
        $opponentProfile = $this->battleReadyProfile($opponent);

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'Sample Bypass',
                'stake_amount' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('battle.sample_pack_status', DjBattle::SAMPLE_PACK_PENDING)
            ->json('battle.uuid');

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/accept")
            ->assertOk();

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/sample-pack/bypass")
            ->assertOk()
            ->assertJsonPath('battle.sample_pack_status', DjBattle::SAMPLE_PACK_BYPASSED)
            ->assertJsonPath('battle.sample_pack_metadata.bypass_source', 'testing');

        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'sample_pack_bypassed',
        ]);
    }

    public function test_recording_entries_open_voting_after_both_djs_submit(): void
    {
        Storage::fake('public');

        [$challenger, $opponent, $battleUuid] = $this->recordingBattle();

        $this->actingAs($challenger)
            ->post("/api/battles/{$battleUuid}/entries", [
                'media' => UploadedFile::fake()->create('challenger-entry.webm', 64, 'video/webm'),
                'title' => 'Challenger Heat',
                'duration_seconds' => 120,
                'recorded_in_browser' => true,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_RECORDING)
            ->assertJsonFragment([
                'status' => 'submitted',
                'title' => 'Challenger Heat',
            ]);

        $this->actingAs($opponent)
            ->post("/api/battles/{$battleUuid}/entries", [
                'media' => UploadedFile::fake()->create('opponent-entry.webm', 64, 'video/webm'),
                'title' => 'Opponent Heat',
                'duration_seconds' => 118,
                'recorded_in_browser' => true,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_VOTING);

        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'entry_submitted',
        ]);
        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'voting_opened',
            'from_status' => DjBattle::STATUS_RECORDING,
            'to_status' => DjBattle::STATUS_VOTING,
        ]);
        $this->assertDatabaseHas('dj_battles', [
            'uuid' => $battleUuid,
            'status' => DjBattle::STATUS_VOTING,
        ]);
        $this->assertDatabaseCount('media_files', 2);
    }

    public function test_local_testing_can_duplicate_submitted_entry_to_open_voting(): void
    {
        Storage::fake('public');

        [$challenger, , $battleUuid] = $this->recordingBattle();

        $this->actingAs($challenger)
            ->post("/api/battles/{$battleUuid}/entries", [
                'media' => UploadedFile::fake()->create('challenger-entry.webm', 64, 'video/webm'),
                'title' => 'Solo Test Heat',
                'duration_seconds' => 120,
                'recorded_in_browser' => true,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_RECORDING);

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/entries/test-duplicate")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_VOTING);

        $this->assertDatabaseHas('dj_battle_entries', [
            'title' => 'Test Duplicate: Solo Test Heat',
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'entry_test_duplicated',
        ]);
        $this->assertDatabaseCount('media_files', 2);
    }

    public function test_fan_can_submit_guided_vote_and_becomes_reward_eligible(): void
    {
        [$challenger, $opponent, $battleUuid] = $this->votingBattle();
        $fan = User::factory()->create();
        $battle = DjBattle::query()
            ->where('uuid', $battleUuid)
            ->firstOrFail();

        $this->actingAs($fan)
            ->postJson("/api/battles/{$battleUuid}/votes", $this->votePayload($battle))
            ->assertCreated()
            ->assertJsonPath('battle.status', DjBattle::STATUS_VOTING)
            ->assertJsonPath('battle.vote_count', 1)
            ->assertJsonPath('battle.viewer_vote.reward_eligible', true);

        $this->assertDatabaseHas('dj_battle_votes', [
            'battle_id' => $battle->id,
            'user_id' => $fan->id,
            'reward_eligible' => true,
        ]);
        $this->assertDatabaseHas('dj_battle_vote_scores', [
            'battle_id' => $battle->id,
            'dj_profile_id' => $challenger->djProfile->id,
            'sample_integration_score' => 8,
            'overall_performance_score' => 9,
            'total_score' => 82,
        ]);
        $this->assertDatabaseHas('dj_battle_vote_scores', [
            'battle_id' => $battle->id,
            'dj_profile_id' => $opponent->djProfile->id,
            'sample_integration_score' => 7,
            'overall_performance_score' => 8,
            'total_score' => 74,
        ]);
        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'fan_vote_submitted',
        ]);

        $this->actingAs($fan)
            ->postJson("/api/battles/{$battleUuid}/votes", $this->votePayload($battle))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vote');
    }

    public function test_competing_djs_cannot_vote_in_their_own_battle(): void
    {
        [$challenger, , $battleUuid] = $this->votingBattle();
        $battle = DjBattle::query()
            ->where('uuid', $battleUuid)
            ->firstOrFail();

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/votes", $this->votePayload($battle))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vote');
    }

    public function test_expired_voting_battle_settles_beta_winner_and_fan_rewards(): void
    {
        [$challenger, $opponent, $fan, $battleUuid] = $this->stakedVotingBattle();
        $battle = DjBattle::query()
            ->where('uuid', $battleUuid)
            ->firstOrFail();

        $this->actingAs($fan)
            ->postJson("/api/battles/{$battleUuid}/votes", $this->votePayload($battle))
            ->assertCreated();

        DjBattle::query()
            ->where('uuid', $battleUuid)
            ->update(['voting_ends_at' => now()->subMinute()]);

        $this->actingAs($fan)
            ->getJson("/api/battles/{$battleUuid}")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_COMPLETED)
            ->assertJsonPath('battle.winner.id', $challenger->djProfile->id)
            ->assertJsonPath('battle.result.total_votes', 1)
            ->assertJsonPath('battle.result.is_draw', false);

        $challengerWallet = $challenger->wallet()->firstOrFail();
        $opponentWallet = $opponent->wallet()->firstOrFail();
        $fanWallet = $fan->wallet()->firstOrFail();

        $this->assertSame(124, $challengerWallet->available_balance);
        $this->assertSame(0, $challengerWallet->locked_balance);
        $this->assertSame(70, $opponentWallet->available_balance);
        $this->assertSame(0, $opponentWallet->locked_balance);
        $this->assertSame(6, $fanWallet->available_balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $challenger->id,
            'type' => WalletService::TYPE_BATTLE_WINNER_REWARD,
            'direction' => 'credit',
            'amount' => 54,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $fan->id,
            'type' => WalletService::TYPE_FAN_REWARD,
            'direction' => 'credit',
            'amount' => 6,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $opponent->id,
            'type' => WalletService::TYPE_BATTLE_STAKE_RELEASED,
            'direction' => 'debit',
            'amount' => 30,
        ]);
        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'fan_rewards_distributed',
        ]);
        $this->assertDatabaseHas('dj_battle_events', [
            'event_type' => 'battle_completed',
            'to_status' => DjBattle::STATUS_COMPLETED,
        ]);
    }

    public function test_battle_leaderboard_ranks_completed_scorecards_by_selected_category(): void
    {
        [$challenger, $opponent, $fan, $battleUuid] = $this->stakedVotingBattle();
        $battle = DjBattle::query()
            ->where('uuid', $battleUuid)
            ->firstOrFail();

        $this->actingAs($fan)
            ->postJson("/api/battles/{$battleUuid}/votes", $this->votePayload($battle))
            ->assertCreated();

        DjBattle::query()
            ->where('uuid', $battleUuid)
            ->update(['voting_ends_at' => now()->subMinute()]);

        $this->getJson('/api/battles/leaderboards?category=overall_performance&min_battles=1')
            ->assertOk()
            ->assertJsonPath('category', 'overall_performance')
            ->assertJsonPath('category_label', 'Overall Performance')
            ->assertJsonPath('minimum_battles', 1)
            ->assertJsonPath('leaderboard.0.dj_id', $challenger->djProfile->id)
            ->assertJsonPath('leaderboard.0.rank', 1)
            ->assertJsonPath('leaderboard.0.selected_category_score', 9)
            ->assertJsonPath('leaderboard.0.average_total_score', 82)
            ->assertJsonPath('leaderboard.0.scored_battles_count', 1)
            ->assertJsonPath('leaderboard.0.wins', 1)
            ->assertJsonPath('leaderboard.1.dj_id', $opponent->djProfile->id)
            ->assertJsonPath('leaderboard.1.selected_category_score', 8)
            ->assertJsonPath('leaderboard.1.losses', 1)
            ->assertJsonCount(0, 'new_competitors');

        $this->getJson('/api/battles/leaderboards?category=overall&verified=false&active=false')
            ->assertOk()
            ->assertJsonPath('category', 'overall');
    }

    public function test_expired_pending_challenge_pauses_and_can_be_extended(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger);
        $opponentProfile = $this->battleReadyProfile($opponent);

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'Slow Reply',
                'stake_amount' => 0,
            ])
            ->assertCreated()
            ->json('battle.uuid');

        DjBattle::query()
            ->where('uuid', $battleUuid)
            ->update([
                'response_due_at' => now()->subMinute(),
                'expires_at' => now()->subMinute(),
            ]);

        $this->actingAs($challenger)
            ->getJson("/api/battles/{$battleUuid}")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_PAUSED);

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/extend")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_PENDING);
    }

    private function recordingBattle(): array
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $this->battleReadyProfile($challenger);
        $opponentProfile = $this->battleReadyProfile($opponent);

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'Recording Test',
                'stake_amount' => 0,
            ])
            ->assertCreated()
            ->json('battle.uuid');

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/accept")
            ->assertOk();

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/ready")
            ->assertOk();

        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/ready")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_RECORDING);

        return [$challenger, $opponent, $battleUuid];
    }

    private function votingBattle(): array
    {
        Storage::fake('public');

        [$challenger, $opponent, $battleUuid] = $this->recordingBattle();

        $this->actingAs($challenger)
            ->post("/api/battles/{$battleUuid}/entries", [
                'media' => UploadedFile::fake()->create('challenger-entry.webm', 64, 'video/webm'),
                'title' => 'Challenger Vote Entry',
                'duration_seconds' => 120,
                'recorded_in_browser' => true,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/entries/test-duplicate")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_VOTING);

        return [$challenger, $opponent, $battleUuid];
    }

    private function stakedVotingBattle(): array
    {
        Storage::fake('public');

        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $fan = User::factory()->create();
        $this->battleReadyProfile($challenger);
        $opponentProfile = $this->battleReadyProfile($opponent);
        $wallets = app(WalletService::class);

        $wallets->credit($challenger, 100, 'token_purchase');
        $wallets->credit($opponent, 100, 'token_purchase');

        $battleUuid = $this->actingAs($challenger)
            ->postJson('/api/battles', [
                'opponent_dj_profile_id' => $opponentProfile->id,
                'battle_type' => 'open_format',
                'title' => 'Settlement Test',
                'stake_amount' => 30,
            ])
            ->assertCreated()
            ->json('battle.uuid');

        $this->actingAs($opponent)->postJson("/api/battles/{$battleUuid}/accept")->assertOk();
        $this->actingAs($challenger)->postJson("/api/battles/{$battleUuid}/ready")->assertOk();
        $this->actingAs($opponent)
            ->postJson("/api/battles/{$battleUuid}/ready")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_RECORDING);

        $this->actingAs($challenger)
            ->post("/api/battles/{$battleUuid}/entries", [
                'media' => UploadedFile::fake()->create('settlement-entry.webm', 64, 'video/webm'),
                'title' => 'Settlement Entry',
                'duration_seconds' => 120,
                'recorded_in_browser' => true,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->actingAs($challenger)
            ->postJson("/api/battles/{$battleUuid}/entries/test-duplicate")
            ->assertOk()
            ->assertJsonPath('battle.status', DjBattle::STATUS_VOTING);

        return [$challenger, $opponent, $fan, $battleUuid];
    }

    private function votePayload(DjBattle $battle): array
    {
        return [
            'watch_order' => [
                $battle->challenger_dj_profile_id,
                $battle->opponent_dj_profile_id,
            ],
            'scores' => [
                [
                    'dj_profile_id' => $battle->challenger_dj_profile_id,
                    'scores' => [
                        'sample_integration' => 8,
                        'scratching_ability' => 7,
                        'mixing_ability' => 8,
                        'blending' => 8,
                        'creativity' => 9,
                        'technical_execution' => 8,
                        'music_selection' => 8,
                        'battle_composition' => 9,
                        'entertainment_value' => 8,
                        'overall_performance' => 9,
                    ],
                ],
                [
                    'dj_profile_id' => $battle->opponent_dj_profile_id,
                    'scores' => [
                        'sample_integration' => 7,
                        'scratching_ability' => 7,
                        'mixing_ability' => 7,
                        'blending' => 8,
                        'creativity' => 8,
                        'technical_execution' => 7,
                        'music_selection' => 7,
                        'battle_composition' => 7,
                        'entertainment_value' => 8,
                        'overall_performance' => 8,
                    ],
                ],
            ],
        ];
    }

    private function battleReadyProfile(User $user, array $overrides = []): DjProfile
    {
        return DjProfile::query()->create([
            'user_id' => $user->id,
            'dj_name' => $overrides['dj_name'] ?? "DJ {$user->id}",
            'handle' => $overrides['handle'] ?? "dj-{$user->id}",
            'bio' => 'Ready for the arena.',
            'dj_type' => 'open_format',
            'battle_enabled' => $overrides['battle_enabled'] ?? true,
            'profile_status' => $overrides['profile_status'] ?? 'active',
            'visibility' => $overrides['visibility'] ?? 'public',
            'published_at' => now(),
        ]);
    }
}
