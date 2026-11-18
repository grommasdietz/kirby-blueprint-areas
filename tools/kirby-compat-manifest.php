<?php

declare(strict_types=1);

// Print an isolated Composer manifest that installs one Kirby version for the compatibility suite.
$version = $argv[1] ?? '';

if ($version === '') {
    fwrite(STDERR, "Usage: php tools/kirby-compat-manifest.php <kirby-version>\n");
    exit(1);
}

$manifest = [
    'name' => 'grommasdietz/kirby-blueprint-areas-compat',
    'description' => 'Temporary Kirby compatibility test project',
    'type' => 'project',
    'require' => [
        'php' => '^8.2',
        'getkirby/cms' => $version,
    ],
    'require-dev' => [
        'phpunit/phpunit' => '^12.5',
    ],
    'autoload' => [
        'psr-4' => [
            'GrommasDietz\\Areas\\' => 'lib/',
        ],
    ],
    'config' => [
        'allow-plugins' => [
            'getkirby/composer-installer' => true,
        ],
        'platform' => [
            'php' => '8.3.16',
        ],
        'sort-packages' => true,
    ],
];

// Exercise the historical minimum without weakening normal dependency policy.
// Keep its known YAML advisory visible to audits; this fixture runs no web server.
if ($version === '5.6.0') {
    $manifest['config']['audit']['ignore']['PKSA-nktx-f1n1-b4yd'] = [
        'apply' => 'block',
        'reason' => 'Isolated Kirby 5.6.0 compatibility fixture only; current locks use patched YAML.',
    ];
}

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
