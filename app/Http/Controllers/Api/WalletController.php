<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->walletFor($request->user());

        return response()->json([
            'wallet' => $this->walletPayload($wallet),
            'transactions' => $wallet->transactions()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (WalletTransaction $transaction): array => $this->transactionPayload($transaction))
                ->values(),
        ]);
    }

    public function transactions(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->walletFor($request->user());
        $transactions = $wallet->transactions()
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'transactions' => collect($transactions->items())
                ->map(fn (WalletTransaction $transaction): array => $this->transactionPayload($transaction))
                ->values(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    private function walletPayload(Wallet $wallet): array
    {
        return [
            'uuid' => $wallet->uuid,
            'available_balance' => (int) $wallet->available_balance,
            'locked_balance' => (int) $wallet->locked_balance,
            'total_balance' => (int) $wallet->available_balance + (int) $wallet->locked_balance,
            'lifetime_earned' => (int) $wallet->lifetime_earned,
            'lifetime_spent' => (int) $wallet->lifetime_spent,
            'lifetime_withdrawn' => (int) $wallet->lifetime_withdrawn,
            'status' => $wallet->status,
        ];
    }

    private function transactionPayload(WalletTransaction $transaction): array
    {
        return [
            'uuid' => $transaction->uuid,
            'type' => $transaction->type,
            'direction' => $transaction->direction,
            'status' => $transaction->status,
            'amount' => (int) $transaction->amount,
            'balance_before' => (int) $transaction->balance_before,
            'balance_after' => (int) $transaction->balance_after,
            'locked_balance_before' => (int) $transaction->locked_balance_before,
            'locked_balance_after' => (int) $transaction->locked_balance_after,
            'description' => $transaction->description,
            'metadata' => $transaction->metadata ?? [],
            'created_at' => optional($transaction->created_at)->toISOString(),
            'completed_at' => optional($transaction->completed_at)->toISOString(),
        ];
    }
}
