<?php

namespace App\Console\Commands;

use App\Services\AffiliateRewardService;
use Illuminate\Console\Command;

class ExpireAffiliateMembershipCredits extends Command
{
    protected $signature = 'affiliate:expire-membership-credits';

    protected $description = 'Expire unused affiliate membership credit rewards that are past their redemption date.';

    public function handle(AffiliateRewardService $rewards): int
    {
        $notified = $rewards->notifyExpiringMembershipCredits();
        $count = $rewards->expireUnusedMembershipCredits();

        $this->info("Affiliate membership credits synced. Expiring soon: {$notified}. Expired: {$count}.");

        return self::SUCCESS;
    }
}
