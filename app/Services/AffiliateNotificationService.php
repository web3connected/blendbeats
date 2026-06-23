<?php

namespace App\Services;

use App\Models\AffiliateAccount;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReward;
use App\Models\User;
use App\Notifications\AffiliateEventNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class AffiliateNotificationService
{
    public function affiliateAccountCreated(AffiliateAccount $account): bool
    {
        $account->loadMissing(['user', 'defaultReferralCode']);

        if (! $account->user) {
            return false;
        }

        return $this->notifyOnce($account, 'affiliate_account_created', $account->user, [
            'event_type' => 'affiliate_account_created',
            'title' => 'Affiliate account created',
            'message' => 'Your BlendBeats affiliate account is active. Share your referral link to start earning membership credits.',
            'icon' => 'badge-check',
            'data' => [
                'affiliate_account_id' => $account->id,
                'referral_code' => $account->defaultReferralCode?->code,
            ],
        ]);
    }

    public function referralSignedUp(AffiliateReferral $referral): bool
    {
        $referral->loadMissing(['affiliateAccount.user', 'referredUser', 'referralCode']);
        $affiliate = $referral->affiliateAccount?->user;

        if (! $affiliate) {
            return false;
        }

        $name = $this->userLabel($referral->referredUser);

        return $this->notifyOnce($referral, 'referral_signed_up', $affiliate, [
            'event_type' => 'affiliate_referral_signed_up',
            'title' => 'Referral signup recorded',
            'message' => "{$name} joined BlendBeats through your referral link.",
            'icon' => 'users',
            'data' => [
                'affiliate_referral_id' => $referral->id,
                'referred_user_id' => $referral->referred_user_id,
                'referral_code' => $referral->referralCode?->code,
            ],
        ]);
    }

    public function referralQualified(AffiliateReferral $referral): bool
    {
        $referral->loadMissing(['affiliateAccount.user', 'referredUser', 'referralCode']);
        $affiliate = $referral->affiliateAccount?->user;

        if (! $affiliate) {
            return false;
        }

        $name = $this->userLabel($referral->referredUser);

        return $this->notifyOnce($referral, 'referral_qualified', $affiliate, [
            'event_type' => 'affiliate_referral_qualified',
            'title' => 'Referral qualified',
            'message' => "{$name}'s subscription qualified your referral.",
            'icon' => 'check-circle',
            'data' => [
                'affiliate_referral_id' => $referral->id,
                'referred_user_id' => $referral->referred_user_id,
                'transaction_type' => $referral->qualified_transaction_type,
                'transaction_id' => $referral->qualified_transaction_id,
            ],
        ]);
    }

    public function membershipCreditIssued(AffiliateReward $reward): bool
    {
        $reward->loadMissing(['affiliateAccount.user', 'referral.referredUser']);
        $affiliate = $reward->affiliateAccount?->user;

        if (! $affiliate || $reward->reward_type !== AffiliateReward::TYPE_MEMBERSHIP_CREDIT) {
            return false;
        }

        return $this->notifyOnce($reward, 'membership_credit_issued', $affiliate, [
            'event_type' => 'affiliate_membership_credit_issued',
            'title' => 'Membership credit issued',
            'message' => "{$reward->membership_credit_days} days of DJ Plus credit has been added to your affiliate rewards.",
            'icon' => 'gift',
            'data' => [
                'affiliate_reward_id' => $reward->id,
                'affiliate_referral_id' => $reward->affiliate_referral_id,
                'membership_credit_days' => $reward->membership_credit_days,
                'expires_at' => $reward->expires_at?->toISOString(),
            ],
        ]);
    }

    public function membershipCreditRedeemed(AffiliateReward $reward, User $user): bool
    {
        if ($reward->reward_type !== AffiliateReward::TYPE_MEMBERSHIP_CREDIT) {
            return false;
        }

        return $this->notifyOnce($reward, 'membership_credit_redeemed', $user, [
            'event_type' => 'affiliate_membership_credit_redeemed',
            'title' => 'Membership credit redeemed',
            'message' => 'Your affiliate membership credit was applied to your free DJ Plus subscription.',
            'icon' => 'check-circle',
            'data' => [
                'affiliate_reward_id' => $reward->id,
                'membership_credit_days' => $reward->membership_credit_days,
                'membership_expires_at' => $reward->metadata['membership_expires_at'] ?? null,
            ],
        ]);
    }

    public function membershipCreditExpiringSoon(AffiliateReward $reward): bool
    {
        $reward->loadMissing('affiliateAccount.user');
        $affiliate = $reward->affiliateAccount?->user;

        if (! $affiliate || $reward->reward_type !== AffiliateReward::TYPE_MEMBERSHIP_CREDIT) {
            return false;
        }

        return $this->notifyOnce($reward, 'membership_credit_expiring_soon', $affiliate, [
            'event_type' => 'affiliate_membership_credit_expiring_soon',
            'title' => 'Membership credit expiring soon',
            'message' => "Your {$reward->membership_credit_days}-day DJ Plus credit expires on {$reward->expires_at?->format('M j, Y')}.",
            'icon' => 'clock',
            'data' => [
                'affiliate_reward_id' => $reward->id,
                'membership_credit_days' => $reward->membership_credit_days,
                'expires_at' => $reward->expires_at?->toISOString(),
            ],
        ]);
    }

    public function membershipCreditExpired(AffiliateReward $reward): bool
    {
        $reward->loadMissing('affiliateAccount.user');
        $affiliate = $reward->affiliateAccount?->user;

        if (! $affiliate || $reward->reward_type !== AffiliateReward::TYPE_MEMBERSHIP_CREDIT) {
            return false;
        }

        return $this->notifyOnce($reward, 'membership_credit_expired', $affiliate, [
            'event_type' => 'affiliate_membership_credit_expired',
            'title' => 'Membership credit expired',
            'message' => 'An unused affiliate membership credit has expired and can no longer be redeemed.',
            'icon' => 'alert-circle',
            'data' => [
                'affiliate_reward_id' => $reward->id,
                'membership_credit_days' => $reward->membership_credit_days,
                'expires_at' => $reward->expires_at?->toISOString(),
            ],
        ]);
    }

    private function notifyOnce(Model $model, string $key, User $user, array $payload): bool
    {
        if (! Schema::hasTable('notifications')) {
            return false;
        }

        $metadata = $model->metadata ?? [];

        if (Arr::get($metadata, "notifications.{$key}_at")) {
            return false;
        }

        $user->notify(new AffiliateEventNotification($payload));

        Arr::set($metadata, "notifications.{$key}_at", now()->toISOString());

        $model->forceFill(['metadata' => $metadata])->save();

        return true;
    }

    private function userLabel(?User $user): string
    {
        return $user?->name ?: $user?->email ?: 'A referred user';
    }
}
