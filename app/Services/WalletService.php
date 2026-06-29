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
            'description' => $context['description'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'created_by_user_id' => $context['created_by_user_id'] ?? null,
            'completed_at' => in_array($status, ['completed', 'released'], true) ? now() : null,
        ]);
    }
}
