<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAdCredit;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserAdCreditService
{
    public const REGISTRATION_SOURCE = 'registration_bonus';

    public function grantRegistrationAdCredit(User $user): ?UserAdCredit
    {
        if (! Schema::hasTable('user_ad_credits')) {
            return null;
        }

        return UserAdCredit::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'credit_type' => 'featured_ad_day',
                'source' => self::REGISTRATION_SOURCE,
            ],
            [
                'code' => $this->codeFor($user),
                'duration_days' => 1,
                'quantity' => 1,
                'remaining_quantity' => 1,
                'discount_type' => 'percent',
                'discount_value' => 100,
                'status' => 'active',
                'granted_at' => now(),
                'metadata' => [
                    'label' => 'Free 1-Day Featured Ad',
                    'description' => 'Registration bonus credit for one free 1-day featured ad campaign.',
                ],
            ],
        );
    }

    private function codeFor(User $user): string
    {
        return 'WELCOME-AD-'.$user->id.'-'.Str::upper(Str::random(6));
    }
}
