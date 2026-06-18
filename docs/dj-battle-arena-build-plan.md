# DJ Battle Arena Build Plan

## Goal

Build the BlendBeats DJ Battle Arena around one proven loop:

```text
Challenge -> Accept -> Record -> Upload -> Vote -> Declare Winner -> Challenge Again
```

The first release should make two DJs battle, let authenticated users vote, and calculate a winner. Weighted voting, prediction rewards, escrow payouts, seasons, and championship brackets should be layered in only after the core loop feels reliable.

## Current Audit

- The app is a Laravel 13 + React SPA.
- Public React routes are registered in `resources/js/React/app.tsx`.
- `/battles` exists, but `resources/js/React/Frontend/pages/battles.tsx` is currently a generic section page.
- The home page already references battle content through static frontend config.
- `dj_profiles` already has `battle_enabled`, `profile_status`, `visibility`, and DJ type values such as `battle_dj`, `turntablist`, and `open_format`.
- `media_files` already stores user media and has an authenticated stream route at `/api/media/files/{file}/stream`.
- The DJ portfolio UI already recognizes `battle_entry` as a future media kind.
- Notifications already exist in Laravel and React.
- Cashier and billing screens exist, but subscription billing is not the same as marketplace escrow or user payouts.
- No battle-specific Laravel models, migrations, controllers, services, routes, or tests were found.

## Product Scope

### MVP Included

- Challenge another public battle-enabled DJ.
- Accept or decline a pending challenge.
- Record a timed battle entry in the browser.
- Upload the captured media as a battle entry.
- Start voting after both entries are submitted.
- Allow one authenticated vote per user per battle.
- Score both entries across battle categories.
- Close voting when the minimum vote count is reached or the voting window expires.
- Calculate and store the winner.
- Show active, pending, voting, and completed battles on `/battles`.
- Show a battle detail page with entries, voting state, and results.
- Send notifications for challenge, acceptance, voting open, and battle completed.

### MVP Not Included

- Real-money escrow.
- Real-money voter rewards.
- Prediction market rewards.
- Seasons.
- Championship brackets.
- Live streaming.
- Retakes.
- Pausing during recording.

Reason:

```text
The first version should prove that battling and voting are fun before money and tournament complexity are added.
```

## Core Domain Rules

- Only authenticated users with an active public DJ profile can challenge or accept battles.
- Both DJs must have `dj_profiles.battle_enabled = true`.
- A DJ cannot challenge themselves.
- A DJ cannot have two active battles against the same opponent.
- A pending challenge expires if the opponent does not accept before the configured deadline.
- An accepted battle expects one entry from each DJ.
- Each DJ gets one submitted entry per battle.
- No pause, restart, or retake in the normal flow.
- Voting opens only after both entries are submitted.
- One verified user account gets one vote per battle.
- Battle participants cannot vote on their own battle in the MVP.
- The winner is calculated from weighted category scores.
- Voting closes when either:
  - `minimum_votes` is reached.
  - `voting_ends_at` passes.
- Admins can cancel, refund, or dispute a battle later through an admin moderation layer.

## Battle Status Flow

```text
pending
accepted
recording
voting
completed
cancelled
declined
expired
disputed
```

Recommended transitions:

```text
pending -> accepted
pending -> declined
pending -> expired
accepted -> recording
recording -> voting
voting -> completed
accepted|recording|voting -> disputed
pending|accepted|recording -> cancelled
```

Keep these transitions inside a backend service so controllers do not manually mutate battle state.

## Database Plan

### `dj_battles`

Stores the challenge and lifecycle state.

```text
id
uuid
challenger_dj_profile_id
opponent_dj_profile_id
created_by_user_id
battle_type
status
title
theme
description
rules
duration_seconds
minimum_votes
bet_amount_cents
currency
winner_dj_profile_id
accepted_at
recording_started_at
voting_started_at
voting_ends_at
completed_at
cancelled_at
created_at
updated_at
```

Suggested indexes:

```text
uuid unique
status, voting_ends_at
challenger_dj_profile_id, status
opponent_dj_profile_id, status
winner_dj_profile_id
```

Suggested `battle_type` values:

```text
mix
scratch
open_format
theme
```

### `dj_battle_entries`

Stores each DJ's submitted battle recording.

```text
id
battle_id
dj_profile_id
user_id
media_file_id
audio_media_file_id
status
title
notes
duration_seconds
recording_started_at
recorded_at
submitted_at
metadata
created_at
updated_at
```

Suggested rules:

```text
unique battle_id, dj_profile_id
media_file_id references media_files.id
audio_media_file_id nullable references media_files.id
```

The browser recording can upload one WebM/MP4 file with audio included for MVP. Keep `audio_media_file_id` nullable for future extracted audio, waveform processing, or alternate playback formats.

### `dj_battle_votes`

Stores one voting session per voter per battle.

```text
id
battle_id
user_id
prediction_pick_dj_profile_id
vote_weight
watched_challenger_entry_at
watched_opponent_entry_at
is_eligible_for_reward
created_at
updated_at
```

Suggested rules:

```text
unique battle_id, user_id
prediction_pick_dj_profile_id nullable references dj_profiles.id
```

Prediction data can be captured later without paying rewards in the MVP.

### `dj_battle_vote_scores`

Stores category scores per entry.

```text
id
vote_id
battle_id
entry_id
dj_profile_id
mixing_score
scratching_score
creativity_score
track_selection_score
total_score
created_at
updated_at
```

Suggested rules:

```text
unique vote_id, entry_id
scores are integers from 1 to 10
```

Why separate this from `dj_battle_votes`:

```text
Each voter must score both DJs. A normalized score table avoids unclear "which DJ did these category columns belong to?" logic.
```

### `dj_battle_results`

Stores the final calculated result for auditability.

```text
id
battle_id
winner_dj_profile_id
challenger_score
opponent_score
total_votes
total_vote_weight
is_draw
calculation_version
calculated_at
created_at
updated_at
```

The result can be derived, but storing it makes leaderboard, notification, and payout workflows safer.

### `dj_battle_events`

Audit log for lifecycle transitions.

```text
id
battle_id
actor_user_id
event_type
from_status
to_status
metadata
created_at
```

Suggested event types:

```text
challenge_created
challenge_accepted
challenge_declined
recording_started
entry_submitted
voting_opened
vote_cast
winner_calculated
battle_cancelled
battle_disputed
```

### Future Money Tables

Do not activate real-money flows in the MVP. Add these tables when escrow and payouts are approved.

#### `battle_escrows`

```text
id
battle_id
status
provider
provider_reference
challenger_amount_cents
opponent_amount_cents
pot_amount_cents
winner_pool_cents
voting_pool_cents
currency
locked_at
released_at
refunded_at
created_at
updated_at
```

Statuses:

```text
pending
funding
funded
locked
releasing
released
refunding
refunded
cancelled
failed
```

#### `battle_voting_pools`

```text
id
battle_id
total_amount_cents
status
minimum_votes
eligible_voter_count
paid_out_at
created_at
updated_at
```

Statuses:

```text
locked
active
calculating
paid
cancelled
refunded
```

#### `battle_voter_rewards`

```text
id
battle_id
user_id
vote_id
amount_cents
status
provider_reference
created_at
updated_at
```

#### `dj_battle_payouts`

```text
id
battle_id
user_id
dj_profile_id
amount_cents
currency
type
status
provider
provider_reference
paid_at
created_at
updated_at
```

Types:

```text
winner
voter_reward
refund
admin_adjustment
```

Important payment note:

```text
Cashier is already present for billing, but user-to-user contest escrow and payouts need a separate provider/ledger design, likely Stripe Connect or another marketplace payout system. Do not ship cash betting, prediction rewards, or voter payouts without legal, tax, fraud, and provider approval.
```

## Backend Files

### Models

```text
app/Models/DjBattle.php
app/Models/DjBattleEntry.php
app/Models/DjBattleVote.php
app/Models/DjBattleVoteScore.php
app/Models/DjBattleResult.php
app/Models/DjBattleEvent.php
```

Future money models:

```text
app/Models/BattleEscrow.php
app/Models/BattleVotingPool.php
app/Models/BattleVoterReward.php
app/Models/DjBattlePayout.php
```

### Controllers

```text
app/Http/Controllers/Api/DjBattleController.php
app/Http/Controllers/Api/DjBattleEntryController.php
app/Http/Controllers/Api/DjBattleVoteController.php
```

Optional later admin controller:

```text
app/Http/Controllers/Admin/BattleModerationController.php
```

### Services

```text
app/Services/DjBattles/BattleLifecycleService.php
app/Services/DjBattles/BattleRecordingService.php
app/Services/DjBattles/BattleScoringService.php
app/Services/DjBattles/BattleVoteWeightService.php
app/Services/DjBattles/BattleNotificationService.php
```

Future money services:

```text
app/Services/DjBattles/BattleEscrowService.php
app/Services/DjBattles/BattlePayoutService.php
app/Services/DjBattles/BattleVoterRewardService.php
```

### Jobs

```text
app/Jobs/DjBattles/ExpirePendingBattleChallenge.php
app/Jobs/DjBattles/CloseBattleVoting.php
app/Jobs/DjBattles/CalculateBattleWinner.php
```

Future money jobs:

```text
app/Jobs/DjBattles/AllocateBattleVoterRewards.php
app/Jobs/DjBattles/ReleaseBattlePayouts.php
```

### Notifications

```text
app/Notifications/BattleChallengeReceivedNotification.php
app/Notifications/BattleChallengeAcceptedNotification.php
app/Notifications/BattleVotingOpenedNotification.php
app/Notifications/BattleCompletedNotification.php
```

Future money notification:

```text
app/Notifications/BattleRewardPaidNotification.php
```

## API Plan

Add battle routes in `routes/api.php`.

```text
GET    /api/battles
GET    /api/battles/{battle:uuid}
POST   /api/battles
POST   /api/battles/{battle:uuid}/accept
POST   /api/battles/{battle:uuid}/decline
POST   /api/battles/{battle:uuid}/cancel

POST   /api/battles/{battle:uuid}/recording/start
POST   /api/battles/{battle:uuid}/entries
GET    /api/battles/{battle:uuid}/entries

POST   /api/battles/{battle:uuid}/votes
GET    /api/battles/{battle:uuid}/results

GET    /api/account/battles
```

Route middleware:

```text
Public list/detail routes:
AddQueuedCookiesToResponse, StartSession

Challenge, accept, recording, upload, vote, account routes:
AddQueuedCookiesToResponse, StartSession, public.auth
```

### `GET /api/battles`

Filters:

```text
status=active|voting|completed|mine
battle_type=mix|scratch|open_format|theme
sort=featured|new|ending_soon|completed
```

Response should include:

```text
battle uuid
status
battle type
DJ cards for challenger and opponent
entry availability
vote count
minimum votes
voting end timestamp
winner summary when completed
```

### `POST /api/battles`

Request:

```json
{
  "opponent_dj_profile_id": 123,
  "battle_type": "open_format",
  "theme": "90s hip-hop blends",
  "duration_seconds": 180,
  "minimum_votes": 25,
  "bet_amount_cents": 0
}
```

MVP behavior:

- Validate current user has a battle-enabled DJ profile.
- Validate opponent has a battle-enabled public active DJ profile.
- Create `dj_battles` with `status = pending`.
- Write `dj_battle_events.challenge_created`.
- Notify opponent.

### `POST /api/battles/{battle}/accept`

Behavior:

- Only the opponent can accept.
- Move `pending -> accepted`.
- Create expected entry placeholders if useful.
- Notify challenger.

### `POST /api/battles/{battle}/recording/start`

Behavior:

- Only a battle participant can start their own recording.
- Battle must be accepted or recording.
- Set battle to `recording` if this is the first recording.
- Store `recording_started_at`.
- Return recording rules:

```json
{
  "duration_seconds": 180,
  "mime_types": ["video/webm", "video/mp4"],
  "max_upload_bytes": 524288000,
  "allow_pause": false,
  "allow_retake": false
}
```

### `POST /api/battles/{battle}/entries`

Use `FormData`.

Request:

```text
media: File
title: string
notes: string|null
duration_seconds: number
```

Behavior:

- Store upload through the existing media storage conventions.
- Create a `media_files` row with `collection = battle_entries`.
- Store `metadata.portfolio.media_kind = battle_entry` if we want the item to appear in portfolio filters.
- Create or update the user's `dj_battle_entries` row.
- Reject replacement if an entry is already submitted.
- If both entries are submitted, transition to voting and notify followers/participants.

### `POST /api/battles/{battle}/votes`

Request:

```json
{
  "prediction_pick_dj_profile_id": 123,
  "scores": [
    {
      "entry_id": 10,
      "mixing_score": 8,
      "scratching_score": 7,
      "creativity_score": 9,
      "track_selection_score": 8
    },
    {
      "entry_id": 11,
      "mixing_score": 7,
      "scratching_score": 8,
      "creativity_score": 8,
      "track_selection_score": 9
    }
  ]
}
```

Behavior:

- Battle must be in `voting`.
- User must be authenticated and cannot be either battle participant.
- User must not have already voted.
- Scores must include exactly both submitted entries.
- Scores must be integers from 1 to 10.
- Store `vote_weight` at the time of voting.
- If vote count reaches `minimum_votes`, dispatch winner calculation.

## Scoring Plan

MVP formula:

```text
entry_total = mixing + scratching + creativity + track_selection
weighted_entry_total = entry_total * vote_weight
final_entry_score = sum(weighted_entry_total) / sum(vote_weight)
```

Winner:

```text
highest final_entry_score wins
```

Draw rule:

```text
If the final score difference is less than 0.01, mark the battle as a draw.
```

Store:

```text
winner_dj_profile_id on dj_battles
full score snapshot on dj_battle_results
```

## Vote Weight Plan

Start simple and cap weights.

```text
new_user = 1
active_user = 2
premium_user = 3
verified_veteran_dj = 5
```

Inputs available now:

- Account age.
- Membership tier through `MembershipTierService`.
- DJ profile verification status.
- Public profile activity.
- Prior valid votes.

Recommended MVP:

```text
Everyone starts at weight 1.
Premium users may be weight 2.
Verified DJs may be weight 3.
Cap all voting at 3 until fraud controls mature.
```

Move to the full weight range after fake account detection, suspicious vote review, and admin tooling exist.

## Recording Plan

Use browser APIs:

```javascript
navigator.mediaDevices.getUserMedia()
MediaRecorder
```

Capture:

```text
webcam
microphone
```

MVP recorder flow:

```text
Request camera and microphone permission.
Show local preview.
Show countdown: 3, 2, 1, GO.
Start MediaRecorder.
Show timer from configured duration down to 00:00.
Auto-stop at 00:00.
Upload immediately.
Show upload progress.
Lock the submitted entry.
```

Rules:

```text
No pause.
No restart.
No retake.
No local edit tools.
```

Technical notes:

- `getUserMedia` requires HTTPS in production.
- Support `video/webm` first because browser MediaRecorder support is strongest there.
- Accept MP4 only when the browser produces it reliably.
- Validate MIME type and file size on the server.
- Store raw recording metadata in `dj_battle_entries.metadata`.
- Add queue processing later for thumbnails, waveform, transcoding, and duration verification.

## Frontend Plan

### Routes

Update `resources/js/React/app.tsx`:

```text
/battles
/battles/:uuid
/battles/:uuid/record
/account/battles
```

### Files

```text
resources/js/React/Frontend/lib/battles.ts
resources/js/React/Frontend/pages/battles.tsx
resources/js/React/Frontend/pages/battles/BattleShowPage.tsx
resources/js/React/Frontend/pages/battles/BattleRecordPage.tsx
resources/js/React/Frontend/pages/auth/AccountBattlesPage.tsx
resources/js/React/Frontend/components/battles/BattleCard.tsx
resources/js/React/Frontend/components/battles/BattleChallengeModal.tsx
resources/js/React/Frontend/components/battles/BattleRecorder.tsx
resources/js/React/Frontend/components/battles/BattleVoteForm.tsx
resources/js/React/Frontend/components/battles/BattleScoreboard.tsx
```

### `/battles`

Replace the current generic section page with the real arena.

Sections:

- Active voting battles.
- Open/pending spotlight when public enough to show.
- Recently completed battles.
- Filters for battle type and status.
- Empty state that pushes users to set up a DJ profile.

### `/battles/:uuid`

Show:

- Battle status.
- DJ cards.
- Battle rules and type.
- Embedded battle entries when available.
- Voting form during voting.
- Scoreboard after completion.
- Challenge metadata and deadlines.

### `/battles/:uuid/record`

Show only to participants.

Controls:

- Camera/mic permission prompt.
- Device selectors if browser provides multiple devices.
- Local preview.
- Countdown.
- Timer.
- Upload progress.
- Submitted state.

### DJ Profile Integration

Update `resources/js/React/Frontend/pages/dj/PublicDjProfilePage.tsx`:

- Show `Challenge DJ` only when:
  - viewer is authenticated.
  - viewer has an active DJ profile.
  - target profile is public and battle-enabled.
  - viewer is not looking at their own profile.
- Open `BattleChallengeModal`.
- Link to the created battle after challenge submission.

### Account Integration

Create `/account/battles` so a logged-in DJ can see:

- Challenges received.
- Challenges sent.
- Battles awaiting recording.
- Battles in voting.
- Completed record.

This keeps urgent battle tasks out of the public arena page.

## Notification Plan

Use the existing notification API and header badge.

Trigger notifications:

- Opponent receives challenge.
- Challenger receives accept/decline.
- Both DJs receive recording opened.
- Both DJs receive voting opened.
- Both DJs receive completed result.
- Optional followers receive voting opened for DJs they follow.

Notification data shape:

```json
{
  "title": "Battle challenge received",
  "message": "DJ Richie challenged you to an Open Format battle.",
  "category": "battles",
  "action_label": "View Battle",
  "action_url": "/battles/{uuid}",
  "icon": "swords"
}
```

## Payment And Voting Pool Plan

### Phase A: No-Money Battles

Start here.

```text
bet_amount_cents = 0
winner_pool_cents = 0
voting_pool_cents = 0
```

This proves the game loop without payment risk.

### Phase B: Platform Credits

Optional bridge before cash.

- Award non-cash site credits or badges.
- Use credits for featured placement discounts or profile boosts only if product wants that.
- Keep credits non-transferable.

### Phase C: Real Escrow

Only after approval.

Flow:

```text
Both DJs fund their side.
Escrow locks the pot.
Battle completes.
Winner pool goes to winner.
Voting pool goes to eligible voters.
Refunds happen on cancellation or failed submission.
```

Suggested split:

```text
80 percent winner pool
20 percent voting pool
```

Voter reward eligibility:

- One vote per verified account.
- Vote includes scores for both entries.
- Voter watched both entries.
- Account is not flagged.
- Vote was submitted before close.
- Prediction pick is correct only when prediction rewards are enabled.

Do not let vote weight directly multiply cash payouts in the first money version. Weight should affect scoring first; payout weighting can be tested later.

## Rankings Plan

Add after completed battles exist.

Track:

```text
wins
losses
draws
win_percentage
current_streak
longest_streak
total_battles
total_votes_received
average_score
total_earnings_cents
```

Implementation options:

1. Derive from completed battles for accuracy.
2. Maintain `dj_battle_rankings` for fast profile and leaderboard reads.

Recommended:

```text
Derive first, materialize later if leaderboard queries get heavy.
```

## Seasons And Championships

Add only after battle volume exists.

### `battle_seasons`

```text
id
name
slug
starts_at
ends_at
status
created_at
updated_at
```

Statuses:

```text
draft
active
completed
archived
```

### Season cadence

```text
Spring
Summer
Fall
Winter
```

### Championship format

```text
Top 8 qualify.
Elite 8.
Final Four.
Championship.
Winner receives badge and Hall of Fame placement.
```

Keep championship battles as normal `dj_battles` with additional bracket metadata instead of building a separate battle engine.

## Admin And Moderation Plan

Needed before money:

- View all battles by status.
- Cancel battle.
- Mark disputed.
- Review submitted media.
- Reset a failed recording only for verified technical failure.
- Ban or flag fake voters.
- Override winner only through an audited admin action.
- View escrow and payout records.
- Export payout/audit data.

Suggested admin route group:

```text
/admin/battles
/admin/battles/{battle}
/admin/battles/{battle}/moderation
```

## Implementation Phases

### Phase 1: Database And Domain Foundation

Build:

- Battle migrations.
- Battle models and relationships.
- Battle lifecycle service.
- Battle event audit logging.
- Factories for tests.

Done when:

- A battle can be created in tests.
- Status transitions are guarded.
- Challenge, accept, decline, and cancel events are audited.

### Phase 2: Challenge System

Build:

- `POST /api/battles`.
- Accept, decline, cancel endpoints.
- `/account/battles`.
- `Challenge DJ` button on public DJ profile.
- Notifications for challenge and response.

Done when:

- DJ A can challenge DJ B.
- DJ B can accept or decline.
- Both users see the battle in account battles.

### Phase 3: Recording And Upload

Build:

- Recording start endpoint.
- React `BattleRecorder`.
- Countdown and fixed timer.
- Auto-stop.
- FormData upload.
- `dj_battle_entries` creation.
- Entry playback on battle detail page.

Done when:

- Each participant can submit one recording.
- The second submitted entry opens voting automatically.
- Submitted entries cannot be replaced.

### Phase 4: Voting

Build:

- Vote form.
- Vote API.
- Score validation.
- One-vote-per-user rule.
- Basic vote weight service.
- Voting progress UI.

Done when:

- Authenticated non-participants can score both entries.
- Duplicate votes are blocked.
- Vote counts update in battle detail and list views.

### Phase 5: Winner Calculation

Build:

- Close voting job.
- Battle scoring service.
- `dj_battle_results`.
- Completed result UI.
- Completion notifications.

Done when:

- Minimum votes or `voting_ends_at` closes the battle.
- Winner and score snapshot are stored.
- Completed battles show final scoreboard.

### Phase 6: Anti-Abuse And Quality Pass

Build:

- Watch-both-entries requirement.
- Account age checks.
- Optional email verification requirement.
- Admin-visible vote audit metadata.
- Suspicious vote flags.
- Rate limits on challenges and votes.

Done when:

- Obvious fake account and spam paths are blocked or reviewable.

### Phase 7: Rankings

Build:

- DJ battle stats on public profiles.
- Battle leaderboard.
- Streak calculation.
- Completed record on account battles.

Done when:

- DJ profiles show battle performance.
- `/battles` can feature top active DJs.

### Phase 8: Escrow And Voting Pool

Build only after legal/payment approval:

- Escrow tables.
- Payment provider integration.
- Funding flow for both DJs.
- Winner pool release.
- Voting pool calculation.
- Refund paths.
- Admin payout review.

Done when:

- No cash leaves the platform without an auditable payout record.
- Failed/cancelled/disputed battles have deterministic refund behavior.

### Phase 9: Seasons And Championships

Build:

- Seasons.
- Seasonal leaderboard.
- Top 8 qualification.
- Bracket metadata.
- Championship badges.
- Hall of Fame.

Done when:

- A full season can start, close, seed a bracket, and archive results.

## First Build Sprint

Week 1 should stay narrow:

1. Create battle migrations and models.
2. Add challenge, accept, decline, and account battle APIs.
3. Replace `/battles` placeholder with real battle list fed by API.
4. Add `Challenge DJ` modal to public profiles.
5. Add recording page with MediaRecorder.
6. Upload one recording per participant.
7. Open voting after both entries submit.
8. Submit votes and calculate a winner manually or through a simple job.

Do not build escrow, predictions, seasons, or championship brackets in week 1.

## Testing Checklist

### Backend

- User without DJ profile cannot challenge.
- Inactive/private DJ profile cannot challenge.
- DJ cannot challenge self.
- Opponent must be battle-enabled.
- Non-opponent cannot accept or decline.
- Accepted battle cannot be accepted twice.
- Participant can submit exactly one entry.
- Non-participant cannot submit an entry.
- Voting does not open before both entries are submitted.
- Participant cannot vote on own battle.
- User cannot vote twice.
- Vote must include both entries.
- Scores outside 1-10 are rejected.
- Winner is calculated correctly with weighted scores.
- Draw is stored when scores are tied.
- Notifications are created for key events.

### Frontend

- Battle list renders empty, loading, error, active, voting, and completed states.
- Challenge modal validates required fields.
- Recorder handles permission denied.
- Countdown starts recording only after GO.
- Timer auto-stops at 00:00.
- Upload progress is visible.
- Submitted state prevents retake.
- Vote form requires every category for both DJs.
- Mobile layout keeps recorder, player, and vote form usable.

### Payment Later

- Escrow cannot release before battle completion.
- Cancelled pending battle refunds both sides.
- Disputed battle does not auto-release funds.
- Voter reward calculation excludes ineligible voters.
- Payout failures remain retryable and audited.

## Open Questions

- Should public users see pending accepted battles before entries are submitted, or only voting/completed battles?
- Should participants be allowed to watch the opponent entry before voting opens?
- Should battle duration always be 3 minutes, or configurable by battle type?
- Should score categories change by battle type?
- Should battle entries automatically appear in DJ portfolios after completion?
- Should voters be required to watch 100 percent of both entries, or a minimum percentage?
- Should battle winner payout include platform fees, and where are those fees disclosed?

## Final Recommendation

Build the arena in this order:

```text
No-money MVP
Weighted voting
Anti-abuse
Rankings
Platform-credit rewards
Approved real-money escrow
Seasons
Championships
```

The core loop is the product. Escrow, predictions, rewards, seasons, and championships should amplify that loop after it is already working.
