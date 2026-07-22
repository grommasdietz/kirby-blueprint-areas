<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Unit;

use Kirby\Cms\App;
use GrommasDietz\Areas\Tests\Support\TestEnvironment;
use GrommasDietz\Areas\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TestEnvironmentTest extends TestCase
{
    public function testBootstrapsPlaygroundSite(): void
    {
        $kirby = TestEnvironment::boot();

        $this->assertInstanceOf(App::class, $kirby);
        $this->assertNotNull($kirby->site());
        $this->assertNotSame('', $kirby->site()->title()->value());
    }

    public function testAllowsConfigOverrides(): void
    {
        $kirby = TestEnvironment::boot([
            'options' => [
                'debug' => true,
            ],
        ]);

        $this->assertTrue($kirby->option('debug'));
    }

    public function testUsesAnIsolatedBlueprintRoot(): void
    {
        $kirby = TestEnvironment::boot();
        $blueprints = $kirby->root('blueprints');

        $this->assertStringEndsWith('/tests/.blueprints', $blueprints);
        $this->assertFileExists($blueprints . '/areas/fields.yml');
        $this->assertNotSame(
            realpath(__DIR__ . '/../../playground/site/blueprints'),
            realpath($blueprints)
        );
    }

    public function testLoadsFixturePluginExtensions(): void
    {
        $kirby = TestEnvironment::boot([
            'testPluginsAfter' => ['proxy-fixtures'],
        ]);

        $this->assertArrayHasKey('proxytest', $kirby->extensions('fields'));
        $this->assertArrayHasKey('proxytest', $kirby->extensions('sections'));
    }

    public function testRejectsUnsafeFixturePluginNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestEnvironment::boot([
            'testPluginsAfter' => ['../outside'],
        ]);
    }

}
