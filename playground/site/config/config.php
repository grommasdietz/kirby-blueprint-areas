<?php

return [
    'debug' => true,
    'panel.vue.compiler' => false,
    'languages' => true,
    'api' => [
        'slug' => getenv('KIRBY_API_SLUG') ?: 'api',
    ],
];
