<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use GrommasDietz\Areas\Tests\TestCase;
use Kirby\Cms\Permissions;
use Kirby\Exception\InvalidArgumentException;

final class BlueprintAreasOptionsTest extends TestCase
{
    public function testRegisteredPluginDefaultsAreAvailable(): void
    {
        $kirby = $this->bootKirby();
        $kirby->impersonate('kirby');

        $options = BlueprintAreas::options();

        $this->assertSame('', $options['panel']['areaPrefix'] ?? null);
        $this->assertFalse($options['panel']['badgeCount'] ?? true);
        $this->assertTrue($options['api']['legacyPayload'] ?? false);
        $this->assertSame(32, $options['api']['maxPayloadDepth'] ?? null);
        $this->assertArrayHasKey('maxPayloadBytes', $options['api'] ?? []);
    }

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
            $this->assertSame('/areas/custom.yml', $view['meta']['blueprintPath'] ?? null);
            $this->assertStringNotContainsString(sys_get_temp_dir(), (string)($view['meta']['blueprintPath'] ?? ''));
        } finally {
            @unlink($blueprintPath);
            @rmdir($root);
        }
    }

    public function testAreaPrefixIsOptInAndKeepsLegacyRolePermissions(): void
    {
        $kirby = $this->bootKirby([
            'options' => [
                'grommasdietz.blueprint-areas' => [
                    'panel' => [
                        'areaPrefix' => 'blueprint-areas-',
                    ],
                ],
            ],
        ]);
        $kirby->impersonate('kirby');

        $this->assertSame('blueprint-areas-fields', BlueprintAreas::menuId('fields'));
        $this->assertArrayHasKey('blueprint-areas-fields', $kirby->extensions('areas'));
        $this->assertSame(
            'blueprint-areas-fields',
            BlueprintAreas::view('fields')['meta']['menuId'] ?? null
        );
        $this->assertTrue(Permissions::$extendedActions['areas']['fields'] ?? false);
        $this->assertTrue(
            Permissions::$extendedActions['areas']['blueprint-areas-fields'] ?? false
        );

        $editor = $kirby->users()->create([
            'email' => 'prefixed-editor-' . uniqid() . '@kirby-blueprint-areas.test',
            'name' => 'Prefixed editor',
            'role' => 'editor',
            'password' => 'test-password',
        ]);

        try {
            $kirby->impersonate($editor->id());
            $this->assertContains('fields', array_column(BlueprintAreas::list(), 'id'));
        } finally {
            $kirby->impersonate('kirby');
            $editor->delete();
        }
    }

    public function testCompetingAreaIdIsExcludedAfterRegistration(): void
    {
        $kirby = $this->bootKirby();
        $kirby->impersonate('kirby');

        $this->assertContains('fields', array_column(BlueprintAreas::listAll(), 'id'));

        $kirby->extend([
            'areas' => [
                'fields' => static fn (): array => [
                    'label' => 'Competing fields area',
                ],
            ],
        ]);

        $this->assertNotContains('fields', array_column(BlueprintAreas::listAll(), 'id'));
    }

    public function testLegacyPayloadCanBeDisabled(): void
    {
        $kirby = $this->bootKirby([
            'options' => [
                'grommasdietz.blueprint-areas' => [
                    'api' => [
                        'legacyPayload' => false,
                    ],
                ],
            ],
        ]);
        $kirby->impersonate('kirby');

        $this->assertSame(
            ['text' => 'Canonical'],
            BlueprintAreas::requestValues(['values' => ['text' => 'Canonical']])
        );

        $this->expectException(InvalidArgumentException::class);
        BlueprintAreas::requestValues(['text' => 'Legacy']);
    }
}
