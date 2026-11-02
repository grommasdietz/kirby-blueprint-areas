<?php

declare(strict_types=1);

use Kirby\Cms\App;

App::plugin('tests/invalid-plugin-blueprints', [
    'blueprints' => [
        'areas/yaml-plugin' => __DIR__ . '/valid.yaml',
        'areas/text-plugin' => __DIR__ . '/ignored.txt',
        'areas/json-plugin' => __DIR__ . '/ignored.json',
        'areas/php-plugin' => __DIR__ . '/ignored.php',
        'areas/array-plugin' => ['title' => 'Array blueprint'],
        'areas/closure-plugin' => static fn (): array => ['title' => 'Callback blueprint'],
        'areas/missing-plugin' => __DIR__ . '/missing.yml',
    ],
]);
