<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserSubscriptionController extends Controller
{
    public function grantFreeSubscription(Request $request, User $user)
    {
        $validated = $request->validate([
            'expires_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user->forceFill([
            'media_storage_tier' => 'dj_plus',
            'paypal_subscription_status' => 'active',
            'billing_provider' => 'internal',
            'paypal_subscription_id' => null,
            'paypal_plan_id' => null,
            'paypal_subscription_approved_at' => now(),
            'comped_subscription_expires_at' => $validated['expires_at'] ?? null,
            'comped_subscription_reason' => $validated['reason'] ?? null,
            'comped_by_user_id' => $request->user()?->id,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Free DJ Plus subscription granted.',
            'user' => $user->fresh(),
        ]);
    }

    public function revokeFreeSubscription(Request $request, User $user)
    {
        if ($user->billing_provider !== 'internal') {
            return response()->json([
                'success' => false,
                'message' => 'Only internal/free subscriptions can be revoked here.',
            ], 422);
        }

        $user->forceFill([
            'media_storage_tier' => 'free',
            'paypal_subscription_status' => 'cancelled',
            'billing_provider' => null,
            'paypal_subscription_approved_at' => null,
            'comped_subscription_expires_at' => null,
            'comped_subscription_reason' => null,
            'comped_by_user_id' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Free DJ Plus subscription revoked.',
            'user' => $user->fresh(),
        ]);
    }
}
