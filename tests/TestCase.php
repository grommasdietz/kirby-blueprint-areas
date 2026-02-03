<?php

declare(strict_types=1);

namespace Kirby\Plugin\Tests;

use Kirby\Cms\App;
use Kirby\Plugin\Tests\Support\TestEnvironment;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case that exposes a helper to boot the Kirby playground.
 */
abstract class TestCase extends BaseTestCase
{
    protected App $kirby;

    /**
     * Boots Kirby for the test suite. Pass overrides to tweak configuration.
     *
     * @param array<string,mixed> $overrides
     */
    protected function bootKirby(array $overrides = []): App
    {
        $this->kirby = TestEnvironment::boot($overrides);

        return $this->kirby;
    }

    protected function tearDown(): void
    {
        if (isset($this->kirby) && method_exists($this->kirby, 'impersonate')) {
            $this->kirby->impersonate(null);
        }

        TestEnvironment::restoreHandlers();

        if (method_exists(App::class, 'destroy')) {
            App::destroy();
        }

        parent::tearDown();
    }
}
