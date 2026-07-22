<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use GrommasDietz\Areas\Tests\TestCase;
use Kirby\Cms\User;
use Kirby\Exception\PermissionException;
use PHPUnit\Framework\Attributes\DataProvider;

final class BlueprintAreasAuthorizationMatrixTest extends TestCase
{
    /** @var list<string> */
    private array $fixtures = [];
    /** @var list<string> */
    private array $users = [];

    protected function tearDown(): void
    {
        if (isset($this->kirby)) {
            $this->kirby->impersonate('kirby');
            foreach ($this->users as $email) {
                $this->kirby->user($email)?->delete();
            }
        }

        foreach ($this->fixtures as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    #[DataProvider('rolePrecedenceProvider')]
    public function testRoleSpecificRulesTakePrecedenceOverWildcard(
        bool $specific,
        bool $wildcard,
        bool $expected
    ): void {
        $area = 'matrix-role-' . ($specific ? 'allow' : 'deny') . '-' . ($wildcard ? 'allow' : 'deny');
        $role = str_replace('-', '', $area);

        $this->writeArea($area);
        $this->writeRole($role, <<<YAML
title: Matrix role
permissions:
  access:
    "*": {$this->yamlBool($wildcard)}
    {$area}: {$this->yamlBool($specific)}
  site:
    access: true
    update: true
YAML);

        $this->bootKirby()->impersonate('kirby');
        $user = $this->createUser($role);
        $this->kirby->impersonate($user->id());

        $this->assertSame($expected, BlueprintAreas::canAccess($area));
        $this->assertSame($expected, in_array($area, array_column(BlueprintAreas::list(), 'id'), true));

        if ($expected) {
            $this->assertSame($area, BlueprintAreas::view($area)['id'] ?? null);
        } else {
            $this->expectException(PermissionException::class);
            BlueprintAreas::view($area);
        }
    }

    /**
     * @return iterable<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function rolePrecedenceProvider(): iterable
    {
        yield 'specific allow overrides wildcard deny' => [true, false, true];
        yield 'specific deny overrides wildcard allow' => [false, true, false];
    }

    #[DataProvider('blueprintPrecedenceProvider')]
    public function testBlueprintSpecificRulesTakePrecedenceOverWildcard(
        bool $specific,
        bool $wildcard,
        bool $expected
    ): void {
        $role = 'matrixblueprint';
        $area = 'matrix-blueprint-' . ($specific ? 'allow' : 'deny') . '-' . ($wildcard ? 'allow' : 'deny');

        $this->writeArea($area, <<<YAML
options:
  access:
    "*": {$this->yamlBool($wildcard)}
    {$role}: {$this->yamlBool($specific)}
YAML);
        $this->writeRole($role, <<<YAML
title: Matrix blueprint
permissions:
  access:
    {$area}: true
  site:
    access: true
    update: true
YAML);

        $this->bootKirby()->impersonate('kirby');
        $user = $this->createUser($role);
        $this->kirby->impersonate($user->id());

        $this->assertSame($expected, BlueprintAreas::canAccess($area));
    }

    /**
     * @return iterable<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function blueprintPrecedenceProvider(): iterable
    {
        yield 'specific allow overrides wildcard deny' => [true, false, true];
        yield 'specific deny overrides wildcard allow' => [false, true, false];
    }

    #[DataProvider('pageReadPermissionProvider')]
    public function testPageAccessAndReadAreIndependentReadGates(
        bool $access,
        bool $read,
        bool $expected
    ): void {
        $area = 'matrix-page-' . (int)$access . '-' . (int)$read;
        $role = str_replace('-', '', $area);

        $this->writeArea($area, 'query: site.find("home")');
        $this->writeRole($role, <<<YAML
title: Matrix page
permissions:
  access:
    {$area}: true
  pages:
    access: {$this->yamlBool($access)}
    read: {$this->yamlBool($read)}
    update: true
YAML);

        $this->bootKirby()->impersonate('kirby');
        $user = $this->createUser($role);
        $this->kirby->impersonate($user->id());

        $this->assertSame($expected, BlueprintAreas::canAccess($area));
    }

    /**
     * @return iterable<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function pageReadPermissionProvider(): iterable
    {
        yield 'both allowed' => [true, true, true];
        yield 'access denied' => [false, true, false];
        yield 'read denied' => [true, false, false];
        yield 'both denied' => [false, false, false];
    }

    public function testSiteReadGateMatchesTheSupportedKirbyPermissionContract(): void
    {
        $area = 'matrix-site-read-gate';
        $role = 'matrixsitereadgate';
        $this->writeArea($area);

        $hasNativeSiteAccess = method_exists($this->bootKirby()->site(), 'isAccessible');
        $legacyAccess = $hasNativeSiteAccess;
        $nativeAccess = !$hasNativeSiteAccess;

        $this->writeRole($role, <<<YAML
title: Matrix Site read gate
permissions:
  access:
    {$area}: true
    site: {$this->yamlBool($legacyAccess)}
  site:
    access: {$this->yamlBool($nativeAccess)}
    update: true
YAML);

        $this->bootKirby()->impersonate('kirby');
        $user = $this->createUser($role);
        $this->kirby->impersonate($user->id());

        $this->assertFalse(BlueprintAreas::canAccess($area));
        $this->expectException(PermissionException::class);
        BlueprintAreas::view($area);
    }

    public function testPageReadAllowedUpdateDeniedCreatesFullyReadOnlyArea(): void
    {
        $area = 'matrix-page-readonly';
        $role = 'matrixpagereadonly';
        $this->writeArea($area, 'query: site.find("home")');
        $this->writeRole($role, <<<YAML
title: Matrix page read-only
permissions:
  access:
    {$area}: true
  pages:
    access: true
    read: true
    update: false
YAML);

        $this->bootKirby()->impersonate('kirby');
        $user = $this->createUser($role);
        $this->kirby->impersonate($user->id());

        $view = BlueprintAreas::view($area);
        $this->assertFalse($view['meta']['canUpdate'] ?? true);
        $this->assertFalse(BlueprintAreas::canAccess($area, true));

        $deniedOperations = 0;
        foreach (['save', 'draft', 'discard'] as $operation) {
            try {
                match ($operation) {
                    'save' => BlueprintAreas::save($area, ['note' => 'Denied']),
                    'draft' => BlueprintAreas::draft($area, ['note' => 'Denied']),
                    'discard' => BlueprintAreas::discard($area),
                };
                $this->fail($operation . ' must require update permission.');
            } catch (PermissionException $exception) {
                $this->assertSame('Not allowed', $exception->getMessage());
                $deniedOperations++;
            }
        }
        $this->assertSame(3, $deniedOperations);
    }

    public function testReadOnlySectionResponsesRemoveMutationCapabilities(): void
    {
        $role = 'matrixsectionreadonly';
        $this->writeRole($role, <<<'YAML'
title: Matrix section read-only
permissions:
  access:
    fields: true
  site:
    access: true
    update: false
  pages:
    access: true
    read: true
    create: true
    delete: true
    move: true
    sort: true
    update: true
YAML);

        $this->bootKirby()->impersonate('kirby');
        $user = $this->createUser($role);
        $this->kirby->impersonate($user->id());

        $fields = BlueprintAreas::section('fields', 'textfields');
        foreach ($fields['fields'] ?? [] as $field) {
            $this->assertTrue($field['disabled'] ?? false);
        }

        $pages = BlueprintAreas::section('fields', 'pages');
        foreach (['add', 'batch', 'create', 'delete', 'drag', 'duplicate', 'move', 'sortable'] as $option) {
            if (array_key_exists($option, $pages['options'] ?? [])) {
                $this->assertFalse($pages['options'][$option], 'Option ' . $option . ' must be disabled.');
            }
        }

        foreach ($pages['data'] ?? [] as $item) {
            foreach (['changeStatus', 'delete', 'duplicate', 'move', 'sort', 'update'] as $permission) {
                if (array_key_exists($permission, $item['permissions'] ?? [])) {
                    $this->assertFalse(
                        $item['permissions'][$permission],
                        'Permission ' . $permission . ' must be disabled.'
                    );
                }
            }
        }
    }

    public function testPrefixedAndLegacyPermissionIdsMustBothRemainAllowed(): void
    {
        $area = 'matrix-prefix';
        $role = 'matrixprefix';
        $this->writeArea($area);
        $this->writeRole($role, <<<YAML
title: Matrix prefix
permissions:
  access:
    pref-{$area}: true
  areas:
    {$area}: false
  site:
    access: true
    update: true
YAML);

        $this->bootKirby([
            'options' => [
                'grommasdietz.blueprint-areas' => [
                    'panel' => ['areaPrefix' => 'pref-'],
                ],
            ],
        ])->impersonate('kirby');
        $user = $this->createUser($role);
        $this->kirby->impersonate($user->id());

        $this->assertFalse(BlueprintAreas::canAccess($area));
        $this->expectException(PermissionException::class);
        BlueprintAreas::view($area);
    }

    private function writeArea(string $area, string $extra = ''): void
    {
        $path = $this->blueprintsRoot() . '/areas/' . $area . '.yml';
        $contents = "title: {$area}\n";
        if ($extra !== '') {
            $contents .= trim($extra) . "\n";
        }
        $contents .= <<<'YAML'
fields:
  note:
    type: text
YAML;
        file_put_contents($path, $contents);
        $this->fixtures[] = $path;
    }

    private function writeRole(string $role, string $contents): void
    {
        $path = $this->blueprintsRoot() . '/users/' . $role . '.yml';
        file_put_contents($path, $contents . "\n");
        $this->fixtures[] = $path;
    }

    private function createUser(string $role): User
    {
        $email = $role . '@kirby-blueprint-areas.test';
        $this->users[] = $email;

        return $this->kirby->users()->create([
            'email' => $email,
            'name' => $role,
            'role' => $role,
            'password' => 'test-password',
        ]);
    }

    private function blueprintsRoot(): string
    {
        $root = realpath(__DIR__ . '/../../playground/site/blueprints');
        if ($root === false) {
            throw new \RuntimeException('Playground blueprints root missing');
        }
        return $root;
    }

    private function yamlBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
