<?php

namespace App\Services;

use App\Models\NewsAutomationLog;
use App\Models\NewsSource;
use App\Models\Post;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class BlendNewsRssImporter
{
    /** @return array{created:int, skipped:int, failed:int, feeds:int} */
    public function import(array $feedUrls, int $limit = 10, bool $dryRun = false): array
    {
        $totals = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'feeds' => count($feedUrls)];

        foreach ($feedUrls as $feedUrl) {
            try {
                $result = $this->importFeed($feedUrl, $limit, $dryRun);
                $totals['created'] += $result['created'];
                $totals['skipped'] += $result['skipped'];
            } catch (Throwable $exception) {
                $totals['failed']++;
                $this->log('failed', 'RSS feed import failed.', [
                    'feed_url' => $feedUrl,
                    'dry_run' => $dryRun,
                ], $exception->getMessage());
            }
        }

        return $totals;
    }

    /** @return array{created:int, skipped:int} */
    private function importFeed(string $feedUrl, int $limit, bool $dryRun): array
    {
        $response = $this->http()->get($feedUrl)->throw();
        $feed = $this->parse($response->body(), $feedUrl);
        $source = $dryRun ? null : $this->source($feed['title'], $feedUrl);
        $created = 0;
        $skipped = 0;

        foreach (array_slice($feed['items'], 0, max(1, $limit)) as $item) {
            if ($this->isPromotional($item['title'], $item['summary'])) {
                $skipped++;
                continue;
            }

            if ($this->duplicateExists($item['guid'], $item['url'])) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $created++;
                continue;
            }

            DB::transaction(function () use ($item, $source, $feedUrl): void {
                Post::query()->create([
                    'author_id' => config('automation.default_author_id'),
                    'news_source_id' => $source?->id,
                    'content_type' => Post::TYPE_NEWS,
                    'title' => $item['title'],
                    'slug' => $this->uniqueSlug($item['title']),
                    'excerpt' => $item['summary'],
                    'content' => $item['summary'],
                    'status' => Post::STATUS_REVIEW,
                    'is_verified' => false,
                    'verification_status' => 'unverified',
                    'importance_level' => 1,
                    'seo' => ['title' => $item['title'], 'description' => $item['summary']],
                    'metadata' => [
                        'source_guid' => $item['guid'],
                        'source_url' => $item['url'],
                        'source_feed_url' => $feedUrl,
                        'source_published_at' => $item['published_at'],
                        'created_by_automation' => true,
                        'workflow_name' => 'Laravel BlendNews RSS Import',
                    ],
                    'reviewed_at' => now(),
                ]);
            });

            $created++;
        }

        $this->log('success', $dryRun ? 'RSS feed dry run completed.' : 'RSS feed import completed.', [
            'feed_url' => $feedUrl,
            'created' => $created,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function http(): PendingRequest
    {
        return Http::accept('application/rss+xml, application/atom+xml, application/xml, text/xml')
            ->withUserAgent('BlendBeats BlendNews RSS Importer/1.0')
            ->timeout((int) config('blendnews.rss.timeout_seconds', 15))
            ->retry(2, 250);
    }

    /** @return array{title:string,items:array<int,array{title:string,url:string,guid:string,summary:string,published_at:?string}>} */
    private function parse(string $xml, string $feedUrl): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $document) {
            throw new RuntimeException('The feed returned invalid XML.');
        }

        if (isset($document->channel)) {
            $title = trim((string) $document->channel->title) ?: parse_url($feedUrl, PHP_URL_HOST);
            $items = [];
            foreach ($document->channel->item as $item) {
                $items[] = $this->rssItem($item);
            }

            return ['title' => (string) $title, 'items' => array_values(array_filter($items))];
        }

        $atom = $document->children('http://www.w3.org/2005/Atom');
        if (! isset($atom->entry)) {
            throw new RuntimeException('The XML document is not a supported RSS or Atom feed.');
        }

        $title = trim((string) $atom->title) ?: (string) parse_url($feedUrl, PHP_URL_HOST);
        $items = [];
        foreach ($atom->entry as $entry) {
            $items[] = $this->atomItem($entry);
        }

        return [
            'title' => $title,
            'items' => array_values(array_filter($items)),
        ];
    }

    /** @return array{title:string,url:string,guid:string,summary:string,published_at:?string}|null */
    private function rssItem(SimpleXMLElement $item): ?array
    {
        $title = trim((string) $item->title);
        $url = trim((string) $item->link);
        $guid = trim((string) $item->guid) ?: $url;
        $summary = trim((string) ($item->description ?: $item->children('http://purl.org/rss/1.0/modules/content/')->encoded));

        return $this->normalizedItem($title, $url, $guid, $summary, trim((string) $item->pubDate));
    }

    /** @return array{title:string,url:string,guid:string,summary:string,published_at:?string}|null */
    private function atomItem(SimpleXMLElement $entry): ?array
    {
        $entry->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $value = static function (SimpleXMLElement $node, string $path): string {
            $matches = $node->xpath($path) ?: [];

            return isset($matches[0]) ? trim((string) $matches[0]) : '';
        };
        $links = $entry->xpath('./atom:link[@rel="alternate"]') ?: $entry->xpath('./atom:link') ?: [];
        $url = isset($links[0]) ? trim((string) $links[0]['href']) : '';

        return $this->normalizedItem(
            $value($entry, './atom:title'),
            $url,
            $value($entry, './atom:id') ?: $url,
            $value($entry, './atom:summary') ?: $value($entry, './atom:content'),
            $value($entry, './atom:published') ?: $value($entry, './atom:updated'),
        );
    }

    /** @return array{title:string,url:string,guid:string,summary:string,published_at:?string}|null */
    private function normalizedItem(string $title, string $url, string $guid, string $summary, string $publishedAt): ?array
    {
        if ($title === '' || $url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $plainSummary = Str::of($summary)->stripTags()->squish()->limit(1000, '')->toString();
        if ($plainSummary === '') {
            $plainSummary = $title;
        }

        return [
            'title' => Str::limit($title, 255, ''),
            'url' => $url,
            'guid' => Str::limit($guid ?: $url, 2048, ''),
            'summary' => $plainSummary,
            'published_at' => $publishedAt !== '' ? $publishedAt : null,
        ];
    }

    private function source(string $name, string $feedUrl): NewsSource
    {
        $host = (string) parse_url($feedUrl, PHP_URL_HOST);
        $slug = Str::slug($host ?: $name) ?: 'rss-source-'.substr(md5($feedUrl), 0, 8);

        return NewsSource::query()->updateOrCreate(['slug' => $slug], [
            'name' => $name ?: $host,
            'url' => $feedUrl,
            'source_type' => 'rss',
            'is_active' => true,
            'metadata' => ['feed_url' => $feedUrl],
        ]);
    }

    private function duplicateExists(string $guid, string $url): bool
    {
        return Post::query()->news()->where(function ($query) use ($guid, $url): void {
            $query->where('metadata->source_guid', $guid)->orWhere('metadata->source_url', $url);
        })->exists();
    }

    private function isPromotional(string $title, string $summary): bool
    {
        $text = Str::lower($title.' '.$summary);

        return collect(config('blendnews.rss.blocked_phrases', []))
            ->contains(fn (string $phrase): bool => $phrase !== '' && str_contains($text, Str::lower($phrase)));
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'rss-story';
        $slug = $base;
        $suffix = 2;

        while (Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function log(string $status, string $message, array $payload, ?string $error = null): void
    {
        if (! Schema::hasTable('news_automation_logs')) {
            return;
        }

        NewsAutomationLog::query()->create([
            'workflow_name' => 'Laravel BlendNews RSS Import',
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
            'error_message' => $error,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
