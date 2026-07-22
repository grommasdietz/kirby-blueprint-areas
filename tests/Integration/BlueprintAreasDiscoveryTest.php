<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use GrommasDietz\Areas\Tests\TestCase;
use Kirby\Cms\Permissions;
use Kirby\Filesystem\Dir;

final class BlueprintAreasDiscoveryTest extends TestCase
{
    public function testYamlBlueprintsAreDiscoveredAndOtherExtensionsIgnored(): void
    {
        $root = $this->temporaryDirectory('extensions');
        file_put_contents($root . '/yaml-area.yaml', "title: YAML area\nfields:\n  note:\n    type: text\n");
        file_put_contents($root . '/ignored.json', '{}');
        file_put_contents($root . '/ignored.txt', 'title: ignored');

        try {
            $this->bootWithRoot($root);
            $this->assertSame(['yaml-area'], array_column(BlueprintAreas::listAll(), 'id'));
        } finally {
            Dir::remove($root);
        }
    }

    public function testYmlTakesPrecedenceWhenBothExtensionsExist(): void
    {
        $root = $this->temporaryDirectory('precedence');
        file_put_contents($root . '/duplicate.yaml', "title: YAML title\nfields:\n  note:\n    type: text\n");
        file_put_contents($root . '/duplicate.yml', "title: YML title\nfields:\n  note:\n    type: text\n");

        try {
            $this->bootWithRoot($root);
            $this->assertSame('YML title', BlueprintAreas::view('duplicate')['title'] ?? null);
        } finally {
            Dir::remove($root);
        }
    }

    public function testMissingBlueprintRootReturnsAnEmptyList(): void
    {
        $root = sys_get_temp_dir() . '/kirby-blueprint-areas-missing-' . uniqid();
        $this->bootWithRoot($root);

        $this->assertSame([], BlueprintAreas::listAll());
        $this->assertSame([], BlueprintAreas::list());
    }

    public function testInvalidAreaPrefixFallsBackToLegacyIds(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.blueprint-areas' => [
                    'panel' => ['areaPrefix' => '../invalid/'],
                ],
            ],
        ])->impersonate('kirby');

        $this->assertSame('fields', BlueprintAreas::menuId('fields'));
        $this->assertArrayHasKey('fields', $this->kirby->extensions('areas'));
    }

    public function testCoreAreaIdsAreNeverRegisteredFromBlueprintFiles(): void
    {
        $path = $this->kirbyBlueprintRoot() . '/site.yml';
        file_put_contents($path, "title: Collision\nfields:\n  note:\n    type: text\n");

        try {
            $this->bootKirby()->impersonate('kirby');
            $this->assertNotContains('site', array_column(BlueprintAreas::listAll(), 'id'));
            $this->assertArrayNotHasKey('site', $this->kirby->extensions('areas'));
        } finally {
            @unlink($path);
        }
    }

    public function testPrefixedCollisionPresentBeforeRegistrationIsExcluded(): void
    {
        putenv('BLUEPRINT_AREAS_TEST_COMPETING_ID=pref-fields');

        try {
            $this->bootKirby([
                'testPluginsBefore' => ['competing-area'],
                'options' => [
                    'grommasdietz.blueprint-areas' => [
                        'panel' => ['areaPrefix' => 'pref-'],
                    ],
                ],
            ])->impersonate('kirby');

            $this->assertNotContains('fields', array_column(BlueprintAreas::listAll(), 'id'));
            $this->assertCount(1, $this->kirby->extensions('areas')['pref-fields'] ?? []);
        } finally {
            putenv('BLUEPRINT_AREAS_TEST_COMPETING_ID');
        }
    }

    public function testRepeatedBootRemovesStalePermissionRegistrations(): void
    {
        $path = $this->kirbyBlueprintRoot() . '/temporary-registration.yml';
        file_put_contents($path, "title: Temporary\nfields:\n  note:\n    type: text\n");

        try {
            $this->bootKirby()->impersonate('kirby');
            $this->assertTrue(Permissions::$extendedActions['areas']['temporary-registration'] ?? false);

            @unlink($path);
            $this->bootKirby()->impersonate('kirby');

            $this->assertArrayNotHasKey(
                'temporary-registration',
                Permissions::$extendedActions['areas'] ?? []
            );
            if (property_exists(Permissions::class, 'extendedAreas')) {
                $this->assertArrayNotHasKey(
                    'temporary-registration',
                    Permissions::$extendedAreas
                );
            }
        } finally {
            @unlink($path);
        }
    }

    public function testExternalBlueprintRootNeverLeaksItsAbsolutePath(): void
    {
        $root = $this->temporaryDirectory('redaction');
        $path = $root . '/external.yaml';
        file_put_contents($path, "title: External\nfields:\n  note:\n    type: text\n");

        try {
            $this->bootWithRoot($root);
            $payload = BlueprintAreas::view('external');
            $encoded = (string)json_encode($payload);

            $this->assertStringNotContainsString(str_replace('\\', '/', $root), str_replace('\\', '/', $encoded));
            $this->assertSame('/areas/external.yaml', $payload['meta']['blueprintPath'] ?? null);
        } finally {
            Dir::remove($root);
        }
    }

    private function bootWithRoot(string $root): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.blueprint-areas' => [
                    'blueprints.root' => $root,
                ],
            ],
        ])->impersonate('kirby');
    }

    private function temporaryDirectory(string $suffix): string
    {
        $root = sys_get_temp_dir() . '/kirby-blueprint-areas-' . $suffix . '-' . uniqid();
        Dir::make($root, true);
        return $root;
    }

    private function kirbyBlueprintRoot(): string
    {
        $root = realpath(__DIR__ . '/../../playground/site/blueprints/areas');
        if ($root === false) {
            throw new \RuntimeException('Blueprint fixture root missing');
        }

        return $root;
    }
}
