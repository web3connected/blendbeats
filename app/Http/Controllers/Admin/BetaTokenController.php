<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class BetaTokenController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with(['wallet', 'djProfile'])
            ->withMax('walletTransactions as last_token_activity_at', 'created_at')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('djProfile', function ($profileQuery) use ($search): void {
                            $profileQuery
                                ->where('dj_name', 'like', "%{$search}%")
                                ->orWhere('handle', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('last_token_activity_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.beta-tokens.index', [
            'users' => $users,
            'search' => $search,
            'settings' => $this->settingsPayload(),
            'stats' => $this->statsPayload(),
        ]);
    }

    public function show(User $user, WalletService $wallets): View
    {
        $wallet = $wallets->walletFor($user);
        $user->load('djProfile');

        return view('admin.beta-tokens.show', [
            'user' => $user,
            'wallet' => $wallet,
            'settings' => $this->settingsPayload(),
            'transactions' => $wallet->transactions()
                ->with(['createdByAdmin', 'related'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function grant(Request $request, User $user, WalletService $wallets): RedirectResponse
    {
        $attributes = $this->tokenActionAttributes($request);

        try {
            $wallets->grantBetaTokens($user, (int) $attributes['amount'], (int) auth('admin')->id(), $attributes['notes'] ?? null);
        } catch (Throwable $exception) {
            return back()->withErrors(['tokens' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', "Granted {$attributes['amount']} beta test tokens to {$user->name}.");
    }

    public function remove(Request $request, User $user, WalletService $wallets): RedirectResponse
    {
        $attributes = $this->tokenActionAttributes($request);

        try {
            $wallets->removeBetaTokens($user, (int) $attributes['amount'], (int) auth('admin')->id(), $attributes['notes'] ?? null);
        } catch (Throwable $exception) {
            return back()->withErrors(['tokens' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', "Removed {$attributes['amount']} beta test tokens from {$user->name}.");
    }

    public function reset(Request $request, User $user, WalletService $wallets): RedirectResponse
    {
        $attributes = $request->validate([
            'amount' => ['required', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $transaction = $wallets->resetBetaBalance($user, (int) $attributes['amount'], (int) auth('admin')->id(), $attributes['notes'] ?? null);
        } catch (Throwable $exception) {
            return back()->withErrors(['tokens' => $exception->getMessage()])->withInput();
        }

        if (! $transaction) {
            return back()->with('status', "{$user->name}'s beta token balance already matched the reset amount.");
        }

        return back()->with('status', "Reset {$user->name}'s beta token balance to {$attributes['amount']}.");
    }

    public function status(Request $request, User $user, WalletService $wallets): RedirectResponse
    {
        $attributes = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        try {
            $wallets->setWalletStatus($user, $attributes['status']);
        } catch (Throwable $exception) {
            return back()->withErrors(['tokens' => $exception->getMessage()]);
        }

        return back()->with('status', "Beta token access for {$user->name} is now {$attributes['status']}.");
    }

    /**
     * @return array{amount: int, notes?: string|null}
     */
    private function tokenActionAttributes(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function settingsPayload(): array
    {
        return [
            'demo_mode' => (bool) config('wallet.beta_token_demo_mode', true),
            'default_beta_tokens' => (int) config('wallet.default_beta_tokens', 500),
            'admin_grants' => (bool) config('wallet.allow_admin_manual_grants', true),
            'battle_staking' => (bool) config('wallet.allow_battle_staking_with_test_tokens', true),
            'fan_rewards' => (bool) config('wallet.allow_fan_reward_simulation', true),
            'winner_rewards' => (bool) config('wallet.allow_winner_payout_simulation', true),
            'withdrawals_enabled' => (bool) config('wallet.withdrawals_enabled', false),
        ];
    }

    private function statsPayload(): array
    {
        $issuedTypes = [
            WalletService::TYPE_BETA_GRANT,
            WalletService::TYPE_BETA_ADJUSTMENT,
            WalletService::TYPE_ADMIN_CORRECTION,
            WalletService::TYPE_BATTLE_WINNER_REWARD,
            WalletService::TYPE_FAN_REWARD,
        ];

        return [
            'total_issued' => (int) WalletTransaction::query()
                ->whereIn('type', $issuedTypes)
                ->whereIn('direction', ['credit', 'adjustment'])
                ->sum('amount'),
            'total_locked' => (int) Wallet::query()->sum('locked_balance'),
            'total_spent' => (int) Wallet::query()->sum('lifetime_spent'),
            'active_wallets' => (int) Wallet::query()->where('status', 'active')->count(),
        ];
    }
}
