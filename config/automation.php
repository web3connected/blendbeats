<?php

return [
    'api_token' => env('AUTOMATION_API_TOKEN'),
    'news_enabled' => env('AUTOMATION_NEWS_ENABLED', false),
    'default_author_id' => env('AUTOMATION_NEWS_DEFAULT_AUTHOR_ID'),
];
