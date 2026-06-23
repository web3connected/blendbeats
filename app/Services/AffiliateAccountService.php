<?php

namespace App\Services;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferralCode;
use App\Models\User;
use Illuminate\Support\Str;

class AffiliateAccountService
{
    public function __construct(private readonly AffiliateNotificationService $notifications) {}

    /**
     * @return array{account: AffiliateAccount, created: bool}
     */
    public function register(User $user, array $profile = []): array
    {
        $account = AffiliateAccount::query()->firstOrNew([
            'user_id' => $user->id,
        ]);

        $created = ! $account->exists;

        if ($created) {
            $account->forceFill([
                'status' => AffiliateAccount::STATUS_ACTIVE,
                'display_name' => $this->displayName($user, $profile),
                'contact_email' => $this->contactEmail($user, $profile),
                'joined_at' => now(),
                'approved_at' => now(),
                'metadata' => [
                    'registration_source' => 'self_service',
                    'profile_established' => true,
                ],
            ])->save();
        }

        $this->ensureDefaultReferralCode($account);

        if ($created) {
            $this->notifications->affiliateAccountCreated($account->fresh(['user', 'defaultReferralCode']) ?? $account);
        }

        return [
            'account' => $account->fresh(['user', 'defaultReferralCode']) ?? $account,
            'created' => $created,
        ];
    }

    public function ensureDefaultReferralCode(AffiliateAccount $account): AffiliateReferralCode
    {
        $existingCode = $account->defaultReferralCode()->first();

        if ($existingCode) {
            return $existingCode;
        }

        return AffiliateReferralCode::query()->create([
            'affiliate_account_id' => $account->id,
            'code' => $this->generateCode($account),
            'label' => 'Default referral link',
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'is_default' => true,
            'starts_at' => now(),
            'metadata' => [
                'registration_source' => 'affiliate_registration',
            ],
        ]);
    }

    private function displayName(User $user, array $profile): string
    {
        $displayName = trim((string) ($profile['display_name'] ?? ''));

        return $displayName !== '' ? $displayName : $user->name;
    }

    private function contactEmail(User $user, array $profile): string
    {
        $contactEmail = trim((string) ($profile['contact_email'] ?? ''));

        return Str::lower($contactEmail !== '' ? $contactEmail : $user->email);
    }

    private function generateCode(AffiliateAccount $account): string
    {
        $base = Str::upper(Str::slug($account->display_name, '-'));

        if ($base === '') {
            $base = 'BLENDBEATS';
        }

        $base = Str::limit($base, 36, '');
        $code = "{$base}-{$account->id}";

        if (! AffiliateReferralCode::query()->where('code', $code)->exists()) {
            return $code;
        }

        return "{$base}-{$account->id}-".Str::upper(Str::random(6));
    }
}
