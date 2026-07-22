<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests;

use GrommasDietz\Areas\Tests\Support\TestEnvironment;
use Kirby\Cms\App;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case that exposes a helper to boot the Kirby playground.
 */
abstract class TestCase extends BaseTestCase
{
    protected App $kirby;

    /**
     * @param array<string,mixed> $overrides
     */
    protected function bootKirby(array $overrides = []): App
    {
        $this->kirby = TestEnvironment::boot($overrides);

        return $this->kirby;
    }

    protected function tearDown(): void
    {
        if (isset($this->kirby)) {
            $this->kirby->impersonate(null);
        }

        App::destroy();

        parent::tearDown();
    }
}
