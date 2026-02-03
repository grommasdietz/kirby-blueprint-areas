<?php

declare(strict_types=1);

namespace Kirby\Plugin\Tests\Unit;

use Kirby\Cms\App;
use Kirby\Plugin\Tests\Support\TestEnvironment;
use Kirby\Plugin\Tests\TestCase;
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
}
