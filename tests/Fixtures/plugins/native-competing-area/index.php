<?php

declare(strict_types=1);

use Kirby\Cms\App;

$areaId = getenv('BLUEPRINT_AREAS_TEST_COMPETING_ID') ?: 'pref-fields';

App::plugin('tests/blueprint-areas-native-competing-area', [
    'areas' => [
        $areaId => static fn (): array => [
            'label' => 'Native competing area',
            'menu' => false,
        ],
    ],
]);
