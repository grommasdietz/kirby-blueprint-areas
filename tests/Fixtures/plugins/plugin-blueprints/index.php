<?php

declare(strict_types=1);

use Kirby\Cms\App;

// Registers area blueprints through Kirby's plugin blueprint extension,
// including an ID that the playground's site/blueprints/areas already owns
// and a nested ID that is not a valid area name.
App::plugin('tests/blueprint-areas-plugin-blueprints', [
    'blueprints' => [
        'areas/plugin-area' => __DIR__ . '/blueprints/areas/plugin-area.yml',
        'areas/fields'      => __DIR__ . '/blueprints/areas/fields.yml',
        'areas/nested/area' => __DIR__ . '/blueprints/areas/plugin-area.yml',
    ],
]);
