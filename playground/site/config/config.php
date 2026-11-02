<?php

return [
    ...(require dirname(__DIR__, 3) . '/tools/playground-secrets.php'),
    'debug' => true,
    'panel.vue.compiler' => false,
    'languages' => true,
    'api' => [
        'slug' => getenv('KIRBY_API_SLUG') ?: 'api',
    ],
];
