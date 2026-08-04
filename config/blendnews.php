<?php

$feeds = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('BLENDNEWS_RSS_FEEDS', '')),
)));

return [
    'images' => [
        'disk' => env('BLENDNEWS_IMAGE_DISK', 'public'),
        'directory' => 'media/blend-news',
        'max_kilobytes' => 5120,
    ],

    'rss' => [
        'enabled' => env('BLENDNEWS_RSS_ENABLED', false),
        'feeds' => $feeds,
        'schedule' => env('BLENDNEWS_RSS_SCHEDULE', '*/30 * * * *'),
        'max_items_per_feed' => (int) env('BLENDNEWS_RSS_MAX_ITEMS', 10),
        'timeout_seconds' => (int) env('BLENDNEWS_RSS_TIMEOUT', 15),
        'blocked_phrases' => [
            'our new',
            'buy now',
            'shop now',
            'on sale',
            'limited-time offer',
            'discount code',
            'giveaway',
            'sponsored:',
        ],
    ],
];
