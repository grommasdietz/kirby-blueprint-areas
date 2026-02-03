#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$outFile = $root . '/THIRD_PARTY_NOTICES.md';

$sections = [];

// -----------------------------
// Composer (PHP)
// -----------------------------
$composerLock = $root . '/composer.lock';
if (is_file($composerLock)) {
    $decoded = json_decode((string) file_get_contents($composerLock), true);
    $packages = $decoded['packages'] ?? [];
    usort($packages, static fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

    $lines = [];
    foreach ($packages as $package) {
        $name = $package['name'] ?? 'unknown';
        $version = $package['version'] ?? 'unknown';
        $licenses = $package['license'] ?? [];
        $homepage = $package['homepage'] ?? '';

        $licenseText = is_array($licenses) ? implode(', ', $licenses) : (string) $licenses;
        $line = "- {$name} ({$version}) — {$licenseText}";
        if ($homepage !== '') {
            $line .= " — {$homepage}";
        }
        $lines[] = $line;
    }

    $sections[] = "## PHP dependencies (Composer)\n\n" . implode("\n", $lines);
}

// -----------------------------
// Node (JS)
// -----------------------------
// Requires installed node_modules.
$rootPackageKey = null;
$packageJsonPath = $root . '/package.json';
if (is_file($packageJsonPath)) {
    $decoded = json_decode((string) file_get_contents($packageJsonPath), true);
    if (is_array($decoded) && is_string($decoded['name'] ?? null) && is_string($decoded['version'] ?? null)) {
        $rootPackageKey = $decoded['name'] . '@' . $decoded['version'];
    }
}

$nodeJson = null;
$nodeCommands = [
    'pnpm exec license-checker --production --json',
    'npm exec --yes license-checker --production --json',
    'npx -y license-checker --production --json',
];

foreach ($nodeCommands as $cmd) {
    $output = [];
    $exit = 0;
    exec($cmd . ' 2>/dev/null', $output, $exit);
    if ($exit === 0 && count($output) > 0) {
        $nodeJson = implode("\n", $output);
        break;
    }
}

if (is_string($nodeJson)) {
    $decoded = json_decode($nodeJson, true);
    if (is_array($decoded)) {
        $items = [];
        foreach ($decoded as $key => $meta) {
            if ($rootPackageKey !== null && $key === $rootPackageKey) {
                continue;
            }

            // key format: name@version
            $license = $meta['licenses'] ?? 'unknown';
            $repo = $meta['repository'] ?? ($meta['url'] ?? '');

            $line = "- {$key} — {$license}";
            if (is_string($repo) && $repo !== '') {
                $line .= " — {$repo}";
            }
            $items[] = $line;
        }

        sort($items, SORT_STRING);
        if (count($items)) {
            $sections[] = "## JS dependencies (Node)\n\n" . implode("\n", $items);
        }
    }
}

$hasSections = count($sections) > 0;
$intro = $hasSections
    ? 'Kirby Area uses following third-party dependencies:'
    : 'Kirby Area does not use any third-party dependencies.';

$header = <<<MD
# Third-party notices

{$intro}

---


MD;

file_put_contents(
    $outFile,
    $header . ($hasSections ? implode("\n\n", $sections) . "\n" : '')
);

fwrite(STDOUT, "Wrote {$outFile}\n");
