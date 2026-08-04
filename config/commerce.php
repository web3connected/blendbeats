<?php

return [
    'images' => [
        'disk' => env('COMMERCE_IMAGE_DISK', 'public'),
        'directory' => 'media/products',
        'max_kilobytes' => 5120,
    ],
];
