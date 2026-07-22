<?php

declare(strict_types=1);

use Kirby\Cms\App;

$areaId = getenv('BLUEPRINT_AREAS_TEST_COMPETING_ID') ?: 'pref-fields';
$areas = [
    $areaId => static fn (): array => [
        'label' => 'Competing test area',
        'menu' => false,
    ],
];

// This fixture models an area that already exists when Blueprint Areas builds
// its dynamic registration list. Apply it immediately to the current App and
// keep the plugin itself extension-free so Kirby does not add it a second time
// during the normal extensionsFromPlugins() pass.
App::plugin('tests/blueprint-areas-competing-area', []);
App::instance()->extend(['areas' => $areas]);
