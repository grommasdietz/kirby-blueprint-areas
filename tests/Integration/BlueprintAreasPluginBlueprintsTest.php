<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use GrommasDietz\Areas\Tests\TestCase;
use Kirby\Cms\Permissions;
use Kirby\Filesystem\Dir;
use PHPUnit\Framework\Attributes\DataProvider;

final class BlueprintAreasPluginBlueprintsTest extends TestCase
{
    #[DataProvider('missingRootProvider')]
    public function testPluginAreasDoNotRequireASiteAreasDirectory(bool $customRoot): void
    {
        $root = sys_get_temp_dir() . '/areas-plugin-only-' . uniqid();
        Dir::make($root, true);

        try {
            $options = $customRoot
                ? ['grommasdietz.blueprint-areas' => ['blueprints.root' => $root . '/missing']]
                : [];
            $this->bootKirby([
                'roots' => ['blueprints' => $root],
                'options' => $options,
                'testPluginsAfter' => ['plugin-blueprints'],
            ])->impersonate('kirby');

            $this->assertContains('plugin-area', array_column(BlueprintAreas::listAll(), 'id'));
            $this->assertArrayHasKey('plugin-area', $this->kirby->extensions('areas'));
            $this->assertSame('Plugin area', BlueprintAreas::view('plugin-area')['title']);
        } finally {
            Dir::remove($root);
        }
    }

    public static function missingRootProvider(): iterable
    {
        yield 'default root without areas folder' => [false];
        yield 'missing custom root' => [true];
    }

    public function testCustomRootOverridesPluginsWithoutAddingDefaultSiteAreas(): void
    {
        $root = sys_get_temp_dir() . '/areas-plugin-custom-' . uniqid();
        Dir::make($root, true);
        file_put_contents($root . '/plugin-area.yaml', "title: Custom plugin area\nfields:\n  custom_note:\n    type: text\n");

        try {
            $this->bootKirby([
                'testPluginsAfter' => ['plugin-blueprints'],
                'options' => ['grommasdietz.blueprint-areas' => ['blueprints.root' => $root]],
            ])->impersonate('kirby');

            $this->assertSame('Custom plugin area', BlueprintAreas::view('plugin-area')['title']);
            $this->assertSame('Plugin fields', BlueprintAreas::view('fields')['title']);
            $this->assertNotContains('home', array_column(BlueprintAreas::listAll(), 'id'));
        } finally {
            Dir::remove($root);
        }
    }

    public function testOnlyYamlFileRegistrationsBecomeAreas(): void
    {
        $this->bootKirby(['testPluginsAfter' => ['invalid-plugin-blueprints']])->impersonate('kirby');

        $ids = array_column(BlueprintAreas::listAll(), 'id');
        $this->assertContains('yaml-plugin', $ids);
        foreach (['text-plugin', 'json-plugin', 'php-plugin', 'array-plugin', 'closure-plugin', 'missing-plugin'] as $id) {
            $this->assertNotContains($id, $ids);
            $this->assertArrayNotHasKey($id, $this->kirby->extensions('areas'));
        }
        $this->assertArrayNotHasKey('areas_test_php_blueprint_loaded', $GLOBALS);
    }

    #[DataProvider('pluginOrderProvider')]
    public function testNativePluginAreasKeepTheirDefaultAccessAcrossBoots(string $order): void
    {
        $this->bootKirby();
        putenv('BLUEPRINT_AREAS_TEST_COMPETING_ID=fields');

        try {
            $this->bootKirby([$order => ['native-competing-area']])->impersonate('kirby');

            $this->assertTrue(Permissions::$extendedAreas['fields'] ?? false);
            $this->assertTrue((new Permissions())->for('access', 'fields'));
        } finally {
            putenv('BLUEPRINT_AREAS_TEST_COMPETING_ID');
            unset(Permissions::$extendedAreas['fields']);
        }
    }

    #[DataProvider('pluginOrderProvider')]
    public function testNativePluginAreasTakePrecedence(string $order): void
    {
        putenv('BLUEPRINT_AREAS_TEST_COMPETING_ID=fields');

        try {
            $this->bootKirby([$order => ['native-competing-area']])->impersonate('kirby');

            $this->assertCount(1, $this->kirby->extensions('areas')['fields']);
            $this->assertNotContains('fields', array_column(BlueprintAreas::listAll(), 'id'));
        } finally {
            putenv('BLUEPRINT_AREAS_TEST_COMPETING_ID');
            unset(Permissions::$extendedAreas['fields']);
        }
    }

    public static function pluginOrderProvider(): iterable
    {
        yield 'plugin loaded before Blueprint Areas' => ['testPluginsBefore'];
        yield 'plugin loaded after Blueprint Areas' => ['testPluginsAfter'];
    }
}
