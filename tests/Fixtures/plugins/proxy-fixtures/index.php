<?php

declare(strict_types=1);

use Kirby\Cms\App;

App::plugin('tests/blueprint-areas-proxy-fixtures', [
    'fields' => [
        'proxytest' => __DIR__ . '/fields/proxytest.php',
    ],
    'sections' => [
        'proxytest' => __DIR__ . '/sections/proxytest.php',
    ],
]);
