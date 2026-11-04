<?php

declare(strict_types=1);

require_once __DIR__ . '/fields/ProxyTestField.php';
\Kirby\Cms\App::plugin('tests/blueprint-areas-proxy-fixtures', [
    'fields' => ['proxytest' => \Tests\BlueprintAreas\ProxyTestField::class],
]);
