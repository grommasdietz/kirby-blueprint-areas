<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\Tests\TestCase;

final class PlaygroundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby()->impersonate('kirby');
    }

    public function testPluginRegistersWithKirby(): void
    {
        $this->assertNotNull($this->kirby->plugin('grommasdietz/blueprint-areas'));
    }

    public function testHomePageCanBeLoaded(): void
    {
        $page = $this->kirby->page('home');

        $this->assertSame('home', $page?->id());
        $this->assertSame('Home', $page?->title()->value());
    }

    public function testUsersCanBeCreatedAndDeleted(): void
    {
        $initialCount = $this->kirby->users()->count();

        $primaryEmail = 'primary-admin-' . uniqid() . '@kirby-blueprint-areas.test';
        $secondaryEmail = 'secondary-admin-' . uniqid() . '@kirby-blueprint-areas.test';

        $primaryAdmin = $this->kirby->users()->create([
            'email' => $primaryEmail,
            'name' => 'Primary Admin',
            'role' => 'admin',
            'password' => 'test-password',
        ]);

        $secondaryAdmin = $this->kirby->users()->create([
            'email' => $secondaryEmail,
            'name' => 'Secondary Admin',
            'role' => 'admin',
            'password' => 'test-password',
        ]);

        $this->assertSame('admin', $primaryAdmin->role()->name());
        $this->assertSame('admin', $secondaryAdmin->role()->name());
        $this->assertCount($initialCount + 2, $this->kirby->users());

        $secondaryAdmin->delete();
        $primaryAdmin->delete();

        $this->assertCount($initialCount, $this->kirby->users());
    }
}
