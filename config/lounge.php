<?php

return [
    'live' => [
        'fallback_track_duration_seconds' => env('LOUNGE_FALLBACK_TRACK_DURATION_SECONDS', 300),
        'sync_interval_seconds' => env('LOUNGE_SYNC_INTERVAL_SECONDS', 30),
    ],
];
