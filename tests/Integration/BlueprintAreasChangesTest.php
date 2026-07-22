<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use GrommasDietz\Areas\Tests\TestCase;
use Kirby\Cms\User;
use Kirby\Exception\PermissionException;

final class BlueprintAreasChangesTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $contentSnapshots = [];
    /** @var list<string> */
    private array $fixtureFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $playground = realpath(__DIR__ . '/../../playground');
        if ($playground === false) {
            throw new \RuntimeException('Playground root missing');
        }

        foreach (['de', 'en'] as $language) {
            $path = $playground . '/content/site.' . $language . '.txt';
            $content = is_file($path) ? file_get_contents($path) : null;
            $this->contentSnapshots[$path] = $content === false ? null : $content;
        }

        $blueprints = $playground . '/site/blueprints';
        $this->writeFixture(
            $blueprints . '/areas/shared-a.yml',
            <<<'YAML'
title: Shared A
fields:
  shared_a:
    type: text
YAML
        );
        $this->writeFixture(
            $blueprints . '/areas/shared-b.yml',
            <<<'YAML'
title: Shared B
fields:
  shared_b:
    type: text
YAML
        );
        $this->writeFixture(
            $blueprints . '/users/changeslimited.yml',
            <<<'YAML'
title: Changes limited
permissions:
  access:
    site: true
  areas:
    "*": false
    shared-a: true
  site:
    access: true
    update: true
YAML
        );
        $this->writeFixture(
            $blueprints . '/users/changesreadonly.yml',
            <<<'YAML'
title: Changes read-only
permissions:
  access:
    shared-a: true
  site:
    access: true
    update: false
YAML
        );

        // Area and role fixtures must exist before Kirby registers dynamic areas
        // and their permission actions for this App instance.
        $this->bootKirby()->impersonate('kirby');
    }

    protected function tearDown(): void
    {
        if (isset($this->kirby)) {
            $this->kirby->impersonate('kirby');
            foreach ([
                'changes-limited@kirby-blueprint-areas.test',
                'changes-readonly@kirby-blueprint-areas.test',
            ] as $email) {
                $this->kirby->user($email)?->delete();
            }
        }

        foreach ($this->contentSnapshots as $path => $content) {
            if ($content === null) {
                @unlink($path);
            } else {
                file_put_contents($path, $content);
            }
        }

        foreach ($this->fixtureFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function testDiscardRemovesOnlyTheSelectedAreasChanges(): void
    {
        BlueprintAreas::draft('shared-a', ['shared_a' => 'Draft A']);
        BlueprintAreas::draft('shared-b', ['shared_b' => 'Draft B']);

        BlueprintAreas::discard('shared-a');

        $changes = $this->changesContent();
        $this->assertArrayNotHasKey('shared_a', $changes);
        $this->assertSame('Draft B', $changes['shared_b'] ?? null);

        $counts = $this->summaryCounts();
        $this->assertSame(0, $counts[BlueprintAreas::menuId('shared-a')] ?? null);
        $this->assertSame(1, $counts[BlueprintAreas::menuId('shared-b')] ?? null);
    }

    public function testPublishClearsOnlyPublishedAreaChanges(): void
    {
        BlueprintAreas::draft('shared-a', ['shared_a' => 'Published A']);
        BlueprintAreas::draft('shared-b', ['shared_b' => 'Draft B']);

        BlueprintAreas::save('shared-a', ['shared_a' => 'Published A']);

        $latest = $this->kirby->site()->content()->toArray();
        $changes = $this->changesContent();

        $this->assertSame('Published A', $latest['shared_a'] ?? null);
        $this->assertArrayNotHasKey('shared_a', $changes);
        $this->assertSame('Draft B', $changes['shared_b'] ?? null);
    }

    public function testReservedMetadataDoesNotKeepAnEmptyChangesVersionAlive(): void
    {
        BlueprintAreas::draft('shared-a', ['shared_a' => 'Draft A']);

        $language = $this->languageCode();
        $changes = $this->kirby->site()->version('changes');
        $content = $changes->read($language);
        if (!is_array($content)) {
            $this->fail('The changes version must exist after drafting an area value.');
        }
        $content['uuid'] = 'test-uuid';
        $content['lock'] = 'test-lock';
        $changes->replace($content, $language);

        BlueprintAreas::discard('shared-a');

        $this->assertFalse($changes->exists($language));
    }

    public function testUnauthorizedAreasAreOmittedFromSummaryAndLockMetadata(): void
    {
        BlueprintAreas::draft('shared-a', ['shared_a' => 'Draft A']);
        BlueprintAreas::draft('shared-b', ['shared_b' => 'Draft B']);

        $user = $this->createUser(
            'changes-limited@kirby-blueprint-areas.test',
            'changeslimited'
        );
        $this->kirby->impersonate($user->id());

        $areaIds = array_column(BlueprintAreas::changesSummary()['areas'] ?? [], 'id');
        $this->assertContains(BlueprintAreas::menuId('shared-a'), $areaIds);
        $this->assertNotContains(BlueprintAreas::menuId('shared-b'), $areaIds);

        $this->expectException(PermissionException::class);
        BlueprintAreas::changesLockForArea('shared-b');
    }

    public function testUnauthenticatedSummaryAndLocksDoNotLeakAreaMetadata(): void
    {
        BlueprintAreas::draft('shared-a', ['shared_a' => 'Draft A']);
        $this->kirby->impersonate('nobody');

        $this->assertSame([], BlueprintAreas::changesSummary()['areas'] ?? null);

        $this->expectException(PermissionException::class);
        BlueprintAreas::changesLockForArea('shared-a');
    }

    public function testReadOnlyUserCanInspectButCannotModifyChangeState(): void
    {
        BlueprintAreas::draft('shared-a', ['shared_a' => 'Draft A']);

        $user = $this->createUser(
            'changes-readonly@kirby-blueprint-areas.test',
            'changesreadonly'
        );
        $this->kirby->impersonate($user->id());

        $counts = $this->summaryCounts();
        $this->assertSame(1, $counts[BlueprintAreas::menuId('shared-a')] ?? null);
        $this->assertTrue(is_array(BlueprintAreas::changesLockForArea('shared-a')));

        try {
            BlueprintAreas::draft('shared-a', ['shared_a' => 'Denied']);
            $this->fail('Read-only roles must not create changes.');
        } catch (PermissionException $exception) {
            $this->assertSame('Not allowed', $exception->getMessage());
        }

        $this->expectException(PermissionException::class);
        BlueprintAreas::discard('shared-a');
    }

    /** @return array<string, int> */
    private function summaryCounts(): array
    {
        $counts = [];
        foreach (BlueprintAreas::changesSummary()['areas'] ?? [] as $area) {
            $counts[(string)$area['id']] = (int)$area['count'];
        }

        return $counts;
    }

    /** @return array<string, mixed> */
    private function changesContent(): array
    {
        $changes = $this->kirby->site()->version('changes');
        return $changes->exists($this->languageCode())
            ? ($changes->read($this->languageCode()) ?? [])
            : [];
    }

    private function languageCode(): string
    {
        return $this->kirby->language()?->code() ?? 'default';
    }

    private function createUser(string $email, string $role): User
    {
        return $this->kirby->users()->create([
            'email' => $email,
            'name' => $role,
            'role' => $role,
            'password' => 'test-password',
        ]);
    }

    private function writeFixture(string $path, string $contents): void
    {
        file_put_contents($path, $contents);
        $this->fixtureFiles[] = $path;
    }
}
