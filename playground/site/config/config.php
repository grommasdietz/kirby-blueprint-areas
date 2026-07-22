<?php

return [
    'debug' => true,
    'languages' => true,
    'api' => [
        'slug' => getenv('KIRBY_API_SLUG') ?: 'api',
    ],
];
