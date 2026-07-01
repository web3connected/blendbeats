<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WalletService
{
    public const TYPE_BETA_GRANT = 'beta_grant';
    public const TYPE_BETA_ADJUSTMENT = 'beta_adjustment';
    public const TYPE_ADMIN_CORRECTION = 'admin_correction';
    public const TYPE_BATTLE_STAKE_LOCKED = 'battle_stake_locked';
    public const TYPE_BATTLE_STAKE_RELEASED = 'battle_stake_released';
    public const TYPE_BATTLE_REFUND = 'battle_refund';
    public const TYPE_BATTLE_WINNER_REWARD = 'battle_winner_reward';
    public const TYPE_FAN_REWARD = 'fan_reward';

    public function walletFor(User $user): Wallet
    {
        return DB::transaction(function () use ($user): Wallet {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($wallet) {
                return $wallet;
            }

            return Wallet::query()->create([
                'user_id' => $user->id,
                'available_balance' => 0,
                'locked_balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_spent' => 0,
                'lifetime_withdrawn' => 0,
                'status' => 'active',
            ]);
        });
    }

    public function grantSignupBetaTokens(User $user): ?WalletTransaction
    {
        if (! (bool) config('wallet.beta_token_demo_mode', true)) {
            return null;
        }

        $amount = (int) config('wallet.default_beta_tokens', 500);

        if ($amount <= 0) {
            return null;
        }

        $wallet = $this->walletFor($user);

        if ($wallet->transactions()
            ->where('type', self::TYPE_BETA_GRANT)
            ->where('description', 'Beta signup test token grant.')
            ->exists()) {
            return null;
        }

        return $this->credit($user, $amount, self::TYPE_BETA_GRANT, [
            'description' => 'Beta signup test token grant.',
            'metadata' => [
                'source' => 'signup_default',
                'demo_mode' => true,
            ],
        ]);
    }

    public function credit(User $user, int $amount, string $type, array $context = []): WalletTransaction
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $type, $context): WalletTransaction {
            $wallet = $this->lockedWalletFor($user);
            $this->assertActiveWallet($wallet);

            $before = $this->snapshot($wallet);

            $wallet->available_balance += $amount;
            $wallet->lifetime_earned += $amount;
            $wallet->save();

            return $this->record($wallet, $type, 'credit', 'completed', $amount, $before, $context);
        });
    }

    public function debit(User $user, int $amount, string $type, array $context = []): WalletTransaction
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $type, $context): WalletTransaction {
            $wallet = $this->lockedWalletFor($user);
            $this->assertActiveWallet($wallet);
            $this->assertAvailableBalance($wallet, $amount);

            $before = $this->snapshot($wallet);

            $wallet->available_balance -= $amount;
            $wallet->lifetime_spent += $amount;
            $wallet->save();

            return $this->record($wallet, $type, 'debit', 'completed', $amount, $before, $context);
        });
    }

    public function lock(User $user, int $amount, string $type, array $context = []): WalletTransaction
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $type, $context): WalletTransaction {
            $wallet = $this->lockedWalletFor($user);
            $this->assertActiveWallet($wallet);
            $this->assertAvailableBalance($wallet, $amount);

            $before = $this->snapshot($wallet);

            $wallet->available_balance -= $amount;
            $wallet->locked_balance += $amount;
            $wallet->save();

            return $this->record($wallet, $type, 'lock', 'locked', $amount, $before, $context);
        });
    }

    public function unlock(User $user, int $amount, string $type, array $context = []): WalletTransaction
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $type, $context): WalletTransaction {
            $wallet = $this->lockedWalletFor($user);
            $this->assertActiveWallet($wallet);

            if (! $wallet->hasLockedBalance($amount)) {
                throw new RuntimeException('Wallet locked balance is too low.');
            }

            $before = $this->snapshot($wallet);

            $wallet->locked_balance -= $amount;
            $wallet->available_balance += $amount;
            $wallet->save();

            return $this->record($wallet, $type, 'unlock', 'released', $amount, $before, $context);
        });
    }

    public function spendLocked(User $user, int $amount, string $type, array $context = []): WalletTransaction
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $type, $context): WalletTransaction {
            $wallet = $this->lockedWalletFor($user);
            $this->assertActiveWallet($wallet);

            if (! $wallet->hasLockedBalance($amount)) {
                throw new RuntimeException('Wallet locked balance is too low.');
            }

            $before = $this->snapshot($wallet);

            $wallet->locked_balance -= $amount;
            $wallet->lifetime_spent += $amount;
            $wallet->save();

            return $this->record($wallet, $type, 'debit', 'completed', $amount, $before, $context);
        });
    }

    public function grantBetaTokens(User $user, int $amount, int $adminId, ?string $notes = null): WalletTransaction
    {
        $this->assertAdminGrantsAllowed();

        return $this->credit($user, $amount, self::TYPE_BETA_GRANT, [
            'description' => $notes ?: 'Admin beta test token grant.',
            'created_by_admin_id' => $adminId,
            'metadata' => [
                'source' => 'admin_manual_grant',
                'demo_mode' => true,
            ],
        ]);
    }

    public function removeBetaTokens(User $user, int $amount, int $adminId, ?string $notes = null): WalletTransaction
    {
        $this->assertAdminGrantsAllowed();

        return $this->debit($user, $amount, self::TYPE_BETA_ADJUSTMENT, [
            'description' => $notes ?: 'Admin beta test token removal.',
            'created_by_admin_id' => $adminId,
            'metadata' => [
                'source' => 'admin_manual_removal',
                'demo_mode' => true,
            ],
        ]);
    }

    public function resetBetaBalance(User $user, int $targetAmount, int $adminId, ?string $notes = null): ?WalletTransaction
    {
        $this->assertAdminGrantsAllowed();

        if ($targetAmount < 0) {
            throw new InvalidArgumentException('Wallet reset amount cannot be negative.');
        }

        return DB::transaction(function () use ($user, $targetAmount, $adminId, $notes): ?WalletTransaction {
            $wallet = $this->lockedWalletFor($user);
            $this->assertActiveWallet($wallet);

            if ((int) $wallet->available_balance === $targetAmount && (int) $wallet->locked_balance === 0) {
                return null;
            }

            $before = $this->snapshot($wallet);
            $beforeTotal = $before['available_balance'] + $before['locked_balance'];

            $wallet->available_balance = $targetAmount;
            $wallet->locked_balance = 0;
            $wallet->save();

            return $this->record($wallet, self::TYPE_ADMIN_CORRECTION, 'adjustment', 'completed', abs($targetAmount - $beforeTotal), $before, [
                'description' => $notes ?: 'Admin beta test token balance reset.',
                'created_by_admin_id' => $adminId,
                'metadata' => [
                    'source' => 'admin_balance_reset',
                    'demo_mode' => true,
                    'target_available_balance' => $targetAmount,
                ],
            ]);
        });
    }

    public function setWalletStatus(User $user, string $status): Wallet
    {
        if (! in_array($status, ['active', 'suspended'], true)) {
            throw new InvalidArgumentException('Unsupported wallet status.');
        }

        return DB::transaction(function () use ($user, $status): Wallet {
            $wallet = $this->lockedWalletFor($user);
            $wallet->forceFill(['status' => $status])->save();

            return $wallet;
        });
    }

    private function lockedWalletFor(User $user): Wallet
    {
        return Wallet::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first()
            ?? $this->walletFor($user);
    }

    private function assertPositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Wallet transaction amount must be positive.');
        }
    }

    private function assertActiveWallet(Wallet $wallet): void
    {
        if (! $wallet->isActive()) {
            throw new RuntimeException('Wallet is not active.');
        }
    }

    private function assertAvailableBalance(Wallet $wallet, int $amount): void
    {
        if (! $wallet->hasAvailableBalance($amount)) {
            throw new RuntimeException('Wallet available balance is too low.');
        }
    }

    /**
     * @return array{available_balance: int, locked_balance: int}
     */
    private function snapshot(Wallet $wallet): array
    {
        return [
            'available_balance' => (int) $wallet->available_balance,
            'locked_balance' => (int) $wallet->locked_balance,
        ];
    }

    /**
     * @param  array{available_balance: int, locked_balance: int}  $before
     */
    private function record(
        Wallet $wallet,
        string $type,
        string $direction,
        string $status,
        int $amount,
        array $before,
        array $context,
    ): WalletTransaction {
        $related = $context['related'] ?? null;

        return WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'type' => $type,
            'direction' => $direction,
            'status' => $status,
            'amount' => $amount,
            'balance_before' => $before['available_balance'],
            'balance_after' => $wallet->available_balance,
            'locked_balance_before' => $before['locked_balance'],
            'locked_balance_after' => $wallet->locked_balance,
            'related_type' => $related instanceof Model ? $related->getMorphClass() : null,
            'related_id' => $related instanceof Model ? $related->getKey() : null,
            'battle_escrow_id' => $context['battle_escrow_id'] ?? null,
            'reverses_transaction_id' => $context['reverses_transaction_id'] ?? null,
            'settlement_group_uuid' => $context['settlement_group_uuid'] ?? null,
            'idempotency_key' => $context['idempotency_key'] ?? null,
            'description' => $context['description'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'created_by_user_id' => $context['created_by_user_id'] ?? null,
            'created_by_admin_id' => $context['created_by_admin_id'] ?? null,
            'completed_at' => in_array($status, ['completed', 'released'], true) ? now() : null,
        ]);
    }

    private function assertAdminGrantsAllowed(): void
    {
        if (! (bool) config('wallet.beta_token_demo_mode', true)
            || ! (bool) config('wallet.allow_admin_manual_grants', true)) {
            throw new RuntimeException('Admin beta token grants are disabled.');
        }
    }
}
