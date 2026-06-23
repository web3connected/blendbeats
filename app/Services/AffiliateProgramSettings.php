<?php

namespace App\Services;

use App\Models\AffiliateReferralEvent;
use App\Models\AffiliateReward;

class AffiliateProgramSettings
{
    public function rewardPlan(): string
    {
        return $this->stringSetting('affiliate.reward_plan', AffiliateReward::TYPE_MEMBERSHIP_CREDIT);
    }

    public function qualificationEvent(): string
    {
        return $this->stringSetting('affiliate.qualification_event', AffiliateReferralEvent::TYPE_SUBSCRIPTION_QUALIFIED);
    }

    public function membershipCreditTier(): string
    {
        return $this->stringSetting(
            'affiliate.membership_credit.tier',
            (string) config('billing.affiliate.membership_credit.tier', 'dj_plus'),
        );
    }

    public function membershipCreditDays(): int
    {
        return $this->positiveIntegerSetting(
            'affiliate.membership_credit.duration_days',
            (int) config('billing.affiliate.membership_credit.days', 30),
        );
    }

    public function membershipCreditExpirationMonths(): int
    {
        return $this->positiveIntegerSetting(
            'affiliate.membership_credit.expires_after_months',
            (int) config('billing.affiliate.membership_credit.expires_after_months', 12),
        );
    }

    public function expiringSoonNotificationDays(): int
    {
        return $this->positiveIntegerSetting(
            'affiliate.notifications.expiring_soon_days',
            (int) config('billing.affiliate.membership_credit.expiring_notice_days', 7),
        );
    }

    public function toArray(): array
    {
        return [
            'reward_plan' => $this->rewardPlan(),
            'qualification_event' => $this->qualificationEvent(),
            'membership_credit_tier' => $this->membershipCreditTier(),
            'membership_credit_days' => $this->membershipCreditDays(),
            'membership_credit_expiration_months' => $this->membershipCreditExpirationMonths(),
            'expiring_soon_notification_days' => $this->expiringSoonNotificationDays(),
        ];
    }

    private function stringSetting(string $key, string $fallback): string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : $fallback;
    }

    private function positiveIntegerSetting(string $key, int $fallback): int
    {
        $value = (int) config($key, $fallback);

        return $value > 0 ? $value : $fallback;
    }
}
