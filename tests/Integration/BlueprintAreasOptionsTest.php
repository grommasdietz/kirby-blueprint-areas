<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use GrommasDietz\Areas\Tests\TestCase;

final class BlueprintAreasOptionsTest extends TestCase
{
    public function testMenuBadgeCountOption(): void
    {
        $kirby = $this->bootKirby([
          'options' => [
            'grommasdietz.blueprint-areas' => [
              'panel' => [
                'badgeCount' => true,
              ],
            ],
          ],
        ]);
        $kirby->impersonate('kirby');

        $view = BlueprintAreas::view('fields');

        $this->assertSame(true, $view['meta']['menuBadgeCount'] ?? null);
    }

    public function testChangesSummaryIncludesMenuBadgeCount(): void
    {
        // With badgeCount enabled
        $kirby = $this->bootKirby([
          'options' => [
            'grommasdietz.blueprint-areas' => [
              'panel' => [
                'badgeCount' => true,
              ],
            ],
          ],
        ]);
        $kirby->impersonate('kirby');

        $summary = BlueprintAreas::changesSummary();

        $this->assertArrayHasKey('menuBadgeCount', $summary);
        $this->assertSame(true, $summary['menuBadgeCount']);
    }

    public function testChangesSummaryMenuBadgeCountDefaultsFalse(): void
    {
        // Without explicit badgeCount option (should default to false)
        $kirby = $this->bootKirby();
        $kirby->impersonate('kirby');

        $summary = BlueprintAreas::changesSummary();

        $this->assertArrayHasKey('menuBadgeCount', $summary);
        $this->assertSame(false, $summary['menuBadgeCount']);
    }

    public function testBlueprintRootOverrideUsesCustomBlueprints(): void
    {
        $root = sys_get_temp_dir() . '/kirby-blueprint-areas-blueprints';
        if (is_dir($root) === false) {
            mkdir($root, 0777, true);
        }

        $blueprintPath = $root . '/custom.yml';
        file_put_contents($blueprintPath, <<<'YAML'
title: Custom Area

fields:
  custom_text:
    label: Custom text
    type: text
YAML);

        try {
            $kirby = $this->bootKirby([
              'options' => [
                'grommasdietz.blueprint-areas' => [
                  'blueprints.root' => $root,
                ],
              ],
            ]);
            $kirby->impersonate('kirby');

            $areas = BlueprintAreas::list();
            $ids = array_map(static fn (array $item): string => (string)$item['id'], $areas);

            $this->assertSame(['custom'], $ids);

            $view = BlueprintAreas::view('custom');
            $this->assertSame('cog', $view['icon'] ?? null);
        } finally {
            @unlink($blueprintPath);
            @rmdir($root);
        }
    }
}
