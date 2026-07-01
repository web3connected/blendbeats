# Battle Escrow Recommended Updates

## Purpose

This document reviews useful ideas from `makeabledk/laravel-escrow` and compares them to the current BlendBeats wallet and battle-stake system.

This is not a recommendation to install or replace our system with that package. The package is useful as a reference pattern only.

## Package Reviewed

Package:

* `makeabledk/laravel-escrow`
* GitHub: https://github.com/makeabledk/laravel-escrow
* Packagist: https://packagist.org/packages/makeabledk/laravel-escrow

Important package notes:

* The package README says it is used internally by Makeable and may not follow SemVer.
* The current tagged version is old and built around Laravel 5.5-era Illuminate packages.
* It depends on old Stripe adapter code and Stripe PHP `~5.0`.
* Its license is CC-BY-SA-4.0, which is not ideal for directly copying code into a proprietary app.
* It should be treated as architecture inspiration, not a dependency.

## What The Package Does Well

The package models escrow as its own first-class domain object.

Core concepts:

* `Escrow` record connected to a customer, provider, and escrowable item.
* Escrow lifecycle states based on timestamps:
  * open
  * committed
  * released
  * cancelled
* `Transaction` records with source and destination polymorphic models.
* Transaction types for:
  * escrow deposit
  * final escrow deposit
  * provider payment
  * platform fee
  * deposit refund
  * account payout
* Explicit actions:
  * commit escrow
  * release escrow
  * cancel escrow
  * deposit escrow
  * deposit provider
  * deposit sales account
* Events around money movement:
  * escrow committed
  * escrow funded
  * escrow released
  * escrow cancelled
  * provider paid
  * refund created
* Reversal behavior for cancelled escrow transactions.

The strongest idea is that escrow is not just a wallet balance. It is a lifecycle with auditable transitions.

## What We Already Have

Our current system is already a better fit for BlendBeats beta battles than this package would be.

Current strengths:

* Every user has a wallet.
* Wallets track available and locked balances.
* Wallet mutations run through `WalletService`.
* Wallet changes use database transactions and `lockForUpdate`.
* Each wallet transaction stores before and after balance snapshots.
* Transactions can link back to related models such as a battle.
* Battle stakes are locked before recording starts.
* Draws and cancellations can refund locked stakes.
* Completed battles can spend locked stakes and credit winner/fan rewards.
* Tests cover wallet locks, refunds, battle settlement, winner rewards, and fan rewards.

Current battle stake flow:

1. DJs receive beta/test tokens.
2. A battle challenge is accepted.
3. Each DJ confirms readiness.
4. Each DJ stake is moved from available balance to locked balance.
5. The battle starts recording.
6. If the battle is cancelled, paused, or drawn, locked stakes are unlocked.
7. If there is a winner, locked stakes are spent and reward credits are created.
8. Fan reward credits are created after voting settlement.

That is good for demo/test-token mode.

## Gaps To Improve Before Real Escrow

The current system works, but escrow is still implied by wallet locks and battle state. Before real-money or higher-value token flows, we should make escrow more explicit.

### 1. Add First-Class Battle Escrow Records

Recommended table: `battle_escrows`

Suggested fields:

* `id`
* `uuid`
* `battle_id`
* `status`
* `escrow_mode`
* `currency_type`
* `stake_amount`
* `challenger_user_id`
* `opponent_user_id`
* `challenger_lock_transaction_id`
* `opponent_lock_transaction_id`
* `winner_user_id`
* `winner_reward_transaction_id`
* `platform_fee_transaction_id`
* `fan_reward_pool_amount`
* `prize_pool_amount`
* `requires_admin_review`
* `settlement_attempts`
* `last_settlement_error`
* `locked_at`
* `released_at`
* `refunded_at`
* `cancelled_at`
* `disputed_at`
* `expires_at`
* `settled_at`
* `resolved_by_user_id`
* `resolved_at`
* `metadata`
* `created_at`
* `updated_at`

Recommended statuses:

* `pending`
* `locked`
* `recording`
* `voting`
* `settling`
* `settled`
* `refunded`
* `cancelled`
* `disputed`

Recommended escrow modes:

* `demo`
* `token`
* `real_money`

Mode behavior:

* `demo` can tolerate testing shortcuts, log missing locks, and mark metadata.
* `token` should enforce wallet consistency and dispute missing locks, but does not require external payment provider settlement.
* `real_money` must fail closed, require strict provider references, and route any mismatch to dispute/admin review.

Why this helps:

* Makes escrow state visible in admin tools.
* Lets us audit battle funds separately from the general wallet ledger.
* Prevents settlement from depending only on inferred wallet state.
* Creates a future bridge for real payment provider escrow.

### 2. Link Refund/Reversal Transactions To Original Transactions

Recommended wallet transaction field:

* `reverses_transaction_id`

Optional related fields:

* `parent_transaction_id`
* `settlement_group_uuid`

Why this helps:

* A refund can point directly to the original lock or debit.
* Admins can see the full chain of money movement.
* Battle settlement can prove every locked stake was either released, refunded, or resolved.

Example:

* `battle_stake_locked`
* `battle_refund` reverses the lock
* `battle_stake_released` consumes the lock

### 3. Add Battle Escrow Events

Recommended domain events:

* `BattleStakeLocked`
* `BattleStakeRefunded`
* `BattleStakeConsumed`
* `BattleEscrowSettling`
* `BattleEscrowSettled`
* `BattleEscrowDisputed`
* `BattleWinnerRewardCredited`
* `BattleFanRewardCredited`

Why this helps:

* Notifications become cleaner.
* Admin audit trails become clearer.
* Queueable provider actions can be attached later.
* Failed settlement can be retried with better visibility.

### 4. Extract Settlement Logic Into A Policy

Recommended service:

* `BattleEscrowSettlementPolicy`

Responsibilities:

* Calculate total stake pool.
* Calculate winner payout.
* Calculate fan reward pool.
* Calculate platform fee, if enabled.
* Handle draw refunds.
* Handle no-vote outcomes.
* Return a settlement plan before mutating balances.

Suggested output:

```php
[
    'winner_user_id' => 123,
    'winner_reward_amount' => 540,
    'fan_reward_pool_amount' => 60,
    'platform_fee_amount' => 0,
    'refunds' => [],
    'debits' => [
        ['user_id' => 1, 'amount' => 300, 'reason' => 'stake_consumed'],
        ['user_id' => 2, 'amount' => 300, 'reason' => 'stake_consumed'],
    ],
    'credits' => [
        ['user_id' => 1, 'amount' => 540, 'reason' => 'winner_reward'],
    ],
]
```

Why this helps:

* Settlement can be tested without writing to the database.
* Fee/reward changes become safer.
* Future real-money compliance checks can inspect the plan before execution.

### 5. Fail Loudly On Missing Locked Stake In Non-Demo Mode

Current beta behavior can skip settlement if a locked stake is missing. That is acceptable for testing, but not for real escrow.

Recommended rule:

* In demo mode, missing locked stake can log a warning and mark metadata.
* In token mode, missing locked stake should mark the escrow as disputed and stop settlement.
* In real-money mode, missing locked stake should fail closed, require admin review, and stop all provider payout actions.

Why this helps:

* Prevents silent accounting mismatches.
* Protects users before funds move.
* Gives admins a clear dispute state.

### 6. Add Admin Escrow Review Screens

Recommended admin views:

* Battle escrow list.
* Battle escrow detail.
* Wallet transaction chain.
* Settlement plan preview.
* Manual dispute flag.
* Manual refund action.
* Manual settlement retry action.
* Admin review queue for `requires_admin_review` escrows.
* Resolution notes with `resolved_by_user_id` and `resolved_at`.

Useful filters:

* status
* escrow mode
* requires admin review
* battle UUID
* user email
* DJ handle
* settlement date
* locked amount
* disputed only

### 7. Add Idempotency Guards

Recommended fields:

* `settlement_uuid`
* `provider_reference`
* `idempotency_key`
* `settlement_attempts`
* `last_settlement_error`

Recommended behavior:

* A battle can only settle once.
* A reward transaction can only be created once per settlement group.
* Retried jobs reuse the same idempotency key.
* Failed settlement attempts increment `settlement_attempts`.
* Failed settlement jobs write `last_settlement_error` before returning the escrow to an admin-review or retryable state.
* Escrows with `expires_at` in the past should be cancelled, refunded, or moved to admin review depending on mode and lock state.

Why this helps:

* Prevents double payouts.
* Makes queue retries safe.
* Prepares us for Stripe Connect or another marketplace provider.
* Gives support/admin users a clear reason for a failed settlement.
* Prevents abandoned escrows from sitting open forever.

### 8. Add Invariant Tests

Recommended tests:

* Stake lock creates one lock transaction per DJ.
* Cancelled battle refunds both original lock transactions.
* Draw battle refunds both original lock transactions.
* Winner settlement consumes both original lock transactions.
* Winner settlement creates one winner reward transaction.
* Fan reward distribution cannot run twice.
* Settlement fails or disputes if locked balance is missing in real escrow mode.
* Settlement records increment `settlement_attempts` on failure.
* Settlement records write `last_settlement_error` on failure.
* Expired escrows move into the correct cleanup state for their `escrow_mode`.
* Admin dispute resolution records `resolved_by_user_id` and `resolved_at`.
* Wallet available plus locked balances match ledger-derived totals.
* Every escrow in `settled` status has complete transaction links.

## What Not To Copy

Do not copy these parts from the package:

* Old Laravel 5.5 package structure.
* Old Stripe PHP v5 adapter.
* Decimal money handling without a clear integer minor-unit standard.
* Direct package transaction model naming.
* Its public API shape.
* Its license-sensitive code.

We should keep our wallet service and build our own battle escrow layer around it.

## Recommended Implementation Order

### Phase 1: Audit Improvements

* Add `reverses_transaction_id` to wallet transactions.
* Add `settlement_group_uuid` to wallet transactions.
* Update lock, refund, spend-locked, winner reward, and fan reward calls to include group metadata.
* Add tests for reversal links.

### Phase 2: Battle Escrow Table

* Add `battle_escrows`.
* Create escrow when a battle is accepted or when readiness phase begins.
* Store `escrow_mode` as `demo`, `token`, or `real_money`.
* Store `expires_at` for escrows that are created before a battle fully starts.
* Add `requires_admin_review`, `settlement_attempts`, `last_settlement_error`, `resolved_by_user_id`, and `resolved_at`.
* Link stake lock transactions to the escrow.
* Expose escrow state in admin-only views.

### Phase 3: Settlement Policy

* Extract settlement math into `BattleEscrowSettlementPolicy`.
* Add pure unit tests for reward/refund/fee scenarios.
* Keep `DjBattleService` responsible for workflow, not payout math.

### Phase 4: Events And Jobs

* Add battle escrow events.
* Add retryable settlement jobs.
* Make reward distribution idempotent.

### Phase 5: Real-Money Readiness

Only after legal, tax, fraud, and provider rules are settled:

* Add marketplace provider integration.
* Add provider references and idempotency keys.
* Add KYC/payout eligibility checks.
* Add withdrawal and dispute workflows.
* Add platform fee accounting.

## Final Recommendation

Keep the current BlendBeats wallet system.

Use the escrow package only as a reference for these ideas:

* first-class escrow lifecycle
* transaction source/destination modeling
* explicit reversal records
* domain events around money movement
* separate settlement policy
* admin-friendly audit trail

The next best technical upgrade is to add reversal links and a `battle_escrows` table while keeping all balance mutation inside `WalletService`.
