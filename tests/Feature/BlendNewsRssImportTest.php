<?php

namespace Tests\Feature;

use App\Models\NewsAutomationLog;
use App\Models\NewsSource;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlendNewsRssImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_imports_rss_items_as_review_drafts_and_skips_duplicates(): void
    {
        Http::fake([
            'https://feeds.example.test/news.xml' => Http::response($this->rssFeed(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $this->artisan('blendnews:rss-import', ['--feed' => ['https://feeds.example.test/news.xml']])
            ->assertSuccessful();

        $post = Post::query()->sole();
        $this->assertSame(Post::STATUS_REVIEW, $post->status);
        $this->assertSame(Post::TYPE_NEWS, $post->content_type);
        $this->assertSame('rss-guid-100', $post->metadata['source_guid']);
        $this->assertSame('https://publisher.example.test/story-100', $post->metadata['source_url']);
        $this->assertFalse($post->is_verified);
        $this->assertNull($post->published_at);
        $this->assertSame('Example DJ News', NewsSource::query()->sole()->name);

        $this->artisan('blendnews:rss-import', ['--feed' => ['https://feeds.example.test/news.xml']])
            ->assertSuccessful();

        $this->assertSame(1, Post::query()->count());
        $this->assertDatabaseHas('news_automation_logs', [
            'workflow_name' => 'Laravel BlendNews RSS Import',
            'status' => 'success',
        ]);
    }

    public function test_dry_run_parses_feed_without_writing_posts_or_sources(): void
    {
        Http::fake([
            'https://feeds.example.test/news.xml' => Http::response($this->rssFeed()),
        ]);

        $this->artisan('blendnews:rss-import', [
            '--feed' => ['https://feeds.example.test/news.xml'],
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, Post::query()->count());
        $this->assertSame(0, NewsSource::query()->count());
        $this->assertSame(1, NewsAutomationLog::query()->count());
    }

    public function test_atom_feeds_are_supported(): void
    {
        Http::fake([
            'https://feeds.example.test/atom.xml' => Http::response($this->atomFeed()),
        ]);

        $this->artisan('blendnews:rss-import', ['--feed' => ['https://feeds.example.test/atom.xml']])
            ->assertSuccessful();

        $this->assertDatabaseHas('posts', [
            'title' => 'A New Turntable Controller Arrives',
            'status' => Post::STATUS_REVIEW,
        ]);
    }

    public function test_failed_feed_is_logged_and_command_fails_cleanly(): void
    {
        Http::fake([
            'https://feeds.example.test/broken.xml' => Http::response('not xml'),
        ]);

        $this->artisan('blendnews:rss-import', ['--feed' => ['https://feeds.example.test/broken.xml']])
            ->assertFailed();

        $this->assertSame(0, Post::query()->count());
        $this->assertDatabaseHas('news_automation_logs', [
            'status' => 'failed',
            'message' => 'RSS feed import failed.',
        ]);
    }

    public function test_promotional_feed_items_are_skipped(): void
    {
        Http::fake([
            'https://feeds.example.test/store.xml' => Http::response(str_replace(
                'New DJ System Announced',
                'Our New DJ USB Drive Is On Sale',
                $this->rssFeed(),
            )),
        ]);

        $this->artisan('blendnews:rss-import', ['--feed' => ['https://feeds.example.test/store.xml']])
            ->assertSuccessful();

        $this->assertSame(0, Post::query()->count());
        $this->assertDatabaseHas('news_automation_logs', ['status' => 'success']);
    }

    private function rssFeed(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example DJ News</title>
    <link>https://publisher.example.test</link>
    <description>DJ industry updates</description>
    <item>
      <guid>rss-guid-100</guid>
      <title>New DJ System Announced</title>
      <link>https://publisher.example.test/story-100</link>
      <description><![CDATA[<p>A compact system with cloud support.</p>]]></description>
      <pubDate>Mon, 03 Aug 2026 12:00:00 GMT</pubDate>
    </item>
  </channel>
</rss>
XML;
    }

    private function atomFeed(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Turntable Wire</title>
  <entry>
    <id>atom-guid-200</id>
    <title>A New Turntable Controller Arrives</title>
    <link rel="alternate" href="https://publisher.example.test/story-200" />
    <summary>Wireless control reaches another DJ platform.</summary>
    <updated>2026-08-03T12:00:00Z</updated>
  </entry>
</feed>
XML;
    }
}
