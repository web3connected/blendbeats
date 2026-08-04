<?php

namespace App\Console\Commands;

use App\Services\BlendNewsRssImporter;
use Illuminate\Console\Command;

class ImportBlendNewsRss extends Command
{
    protected $signature = 'blendnews:rss-import
        {--feed=* : Feed URL; repeat the option for multiple feeds}
        {--limit= : Maximum items processed per feed}
        {--dry-run : Parse and report without creating drafts}';

    protected $description = 'Import configured RSS or Atom feeds into BlendNews review drafts';

    public function handle(BlendNewsRssImporter $importer): int
    {
        $feeds = array_values(array_filter($this->option('feed') ?: config('blendnews.rss.feeds', [])));
        $limit = max(1, (int) ($this->option('limit') ?: config('blendnews.rss.max_items_per_feed', 10)));

        if ($feeds === []) {
            $this->error('No RSS feeds configured. Set BLENDNEWS_RSS_FEEDS or pass --feed=<url>.');

            return self::FAILURE;
        }

        $result = $importer->import($feeds, $limit, (bool) $this->option('dry-run'));
        $this->table(['Feeds', 'Created', 'Skipped', 'Failed', 'Mode'], [[
            $result['feeds'], $result['created'], $result['skipped'], $result['failed'],
            $this->option('dry-run') ? 'dry run' : 'review drafts',
        ]]);

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
