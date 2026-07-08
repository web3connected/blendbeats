<?php

namespace App\Console\Commands;

use App\Services\Live\LiveService;
use Illuminate\Console\Command;

class EndExpiredLiveStreams extends Command
{
    protected $signature = 'live:end-expired';

    protected $description = 'End active live streams that have exceeded their plan duration limit.';

    public function handle(LiveService $live): int
    {
        $ended = $live->endExpiredStreams();

        $this->info("Ended {$ended} expired live stream".($ended === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
