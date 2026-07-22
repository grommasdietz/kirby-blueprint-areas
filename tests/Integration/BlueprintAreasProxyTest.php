<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use GrommasDietz\Areas\Tests\TestCase;
use Kirby\Cms\Api;
use Kirby\Cms\User;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BlueprintAreasProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby([
            'testPluginsAfter' => ['proxy-fixtures'],
            'testBlueprints' => [
                'areas/proxy-fixture.yml' => <<<'YAML'
title: Proxy fixture

sections:
  form:
    type: fields
    fields:
      proxy:
        type: proxytest
  proxysection:
    type: proxytest
YAML,
                'users/proxyreadonly.yml' => <<<'YAML'
title: Proxy read-only

permissions:
  access:
    proxy-fixture: true
  site:
    access: true
    update: false
YAML,
            ],
        ])->impersonate('kirby');
    }

    protected function tearDown(): void
    {
        if (isset($this->kirby)) {
            $this->kirby->impersonate('kirby');
            $user = $this->kirby->user('proxy-readonly@kirby-blueprint-areas.test');
            $user?->delete();
        }

        parent::tearDown();
    }

    #[DataProvider('invalidProxyPathProvider')]
    public function testFieldProxyRejectsTraversalAndEncodedSeparators(string $path): void
    {
        $this->expectException(NotFoundException::class);
        BlueprintAreas::fieldApi('proxy-fixture', 'proxy', $path, $this->api('GET'));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidProxyPathProvider(): iterable
    {
        yield 'parent segment' => ['..'];
        yield 'current segment' => ['.'];
        yield 'nested parent segment' => ['nested/../value'];
        yield 'encoded parent segment' => ['%2e%2e'];
        yield 'double encoded parent segment' => ['%252e%252e'];
        yield 'encoded slash' => ['%2f'];
        yield 'double encoded slash' => ['%252f'];
        yield 'backslash' => ['nested\\value'];
        yield 'encoded backslash' => ['%5c'];
        yield 'nul byte' => ['%00'];
        yield 'empty segment' => ['nested//value'];
        yield 'control character' => ["nested/\x01value"];
    }

    public function testUnknownFieldAndSectionAreNotFound(): void
    {
        try {
            BlueprintAreas::fieldApi('proxy-fixture', 'missing', 'nested/value', $this->api('GET'));
            $this->fail('Unknown field must return not found.');
        } catch (NotFoundException $exception) {
            $this->assertSame('Field not found', $exception->getMessage());
        }

        $this->expectException(NotFoundException::class);
        BlueprintAreas::sectionApi('proxy-fixture', 'missing', 'nested/value', $this->api('GET'));
    }

    public function testUnknownNestedRouteIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        BlueprintAreas::fieldApi('proxy-fixture', 'proxy', 'missing', $this->api('GET'));
    }

    #[DataProvider('unsupportedMethodProvider')]
    public function testUnsupportedProxyMethodsReturnMethodNotAllowed(string $method): void
    {
        try {
            BlueprintAreas::fieldApi('proxy-fixture', 'proxy', 'nested/value', $this->api($method));
            $this->fail('Unsupported proxy method must be rejected.');
        } catch (KirbyException $exception) {
            $this->assertSame('Unsupported proxy request method: ' . $method, $exception->getMessage());
            $this->assertSame(405, $exception->getHttpCode());
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsupportedMethodProvider(): iterable
    {
        yield 'options' => ['OPTIONS'];
        yield 'trace' => ['TRACE'];
        yield 'connect' => ['CONNECT'];
    }

    public function testKnownRouteWithWrongMethodReturnsMethodNotAllowed(): void
    {
        try {
            BlueprintAreas::fieldApi('proxy-fixture', 'proxy', 'nested/value', $this->api('POST'));
            $this->fail('Known route with a wrong method must return 405.');
        } catch (KirbyException $exception) {
            $this->assertSame(405, $exception->getHttpCode());
        }
    }

    public function testNestedFieldAndSectionRoutesResolve(): void
    {
        $field = BlueprintAreas::fieldApi(
            'proxy-fixture',
            'proxy',
            'nested/value',
            $this->api('GET')
        );
        $section = BlueprintAreas::sectionApi(
            'proxy-fixture',
            'proxysection',
            'nested/value',
            $this->api('GET')
        );

        $this->assertSame('value', $field['value'] ?? null);
        $this->assertSame('value', $section['value'] ?? null);
    }

    public function testHeadFallsBackToGetRouteWithoutEscalatingAuthorization(): void
    {
        $result = BlueprintAreas::fieldApi(
            'proxy-fixture',
            'proxy',
            'nested/value',
            $this->api('HEAD')
        );

        $this->assertSame('GET', $result['method'] ?? null);
    }

    public function testRequestDataIsForwardedUnchangedToNestedApi(): void
    {
        $requestData = [
            'query' => ['page' => 2],
            'body' => ['payload' => 'value'],
            'files' => ['upload' => ['name' => 'fixture.txt']],
        ];

        $result = BlueprintAreas::fieldApi(
            'proxy-fixture',
            'proxy',
            'echo/forwarded',
            $this->api('POST', $requestData)
        );

        $this->assertSame($requestData, $result['data'] ?? null);
        $this->assertSame('forwarded', $result['value'] ?? null);
    }

    public function testExplicitReadPostIsAllowedForReadOnlyRole(): void
    {
        $this->impersonateReadOnlyUser();

        $field = BlueprintAreas::fieldApi(
            'proxy-fixture',
            'proxy',
            'read-post',
            $this->api('POST', ['body' => ['query' => 'safe']])
        );
        $section = BlueprintAreas::sectionApi(
            'proxy-fixture',
            'proxysection',
            'read-post',
            $this->api('POST')
        );

        $this->assertSame('read-post', $field['route'] ?? null);
        $this->assertSame('read-post', $section['route'] ?? null);
    }

    public function testExplicitUpdateGetIsDeniedForReadOnlyRole(): void
    {
        $this->impersonateReadOnlyUser();

        try {
            BlueprintAreas::fieldApi(
                'proxy-fixture',
                'proxy',
                'write-get',
                $this->api('GET')
            );
            $this->fail('Explicit update metadata must override GET read semantics.');
        } catch (PermissionException $exception) {
            $this->assertSame('Not allowed', $exception->getMessage());
        }

        $this->expectException(PermissionException::class);
        BlueprintAreas::sectionApi(
            'proxy-fixture',
            'proxysection',
            'write-get',
            $this->api('GET')
        );
    }

    public function testDefaultMutatingRoutesAreDeniedForReadOnlyRole(): void
    {
        $this->impersonateReadOnlyUser();

        try {
            BlueprintAreas::fieldApi(
                'proxy-fixture',
                'proxy',
                'write-post',
                $this->api('POST')
            );
            $this->fail('POST must require update permission by default.');
        } catch (PermissionException $exception) {
            $this->assertSame('Not allowed', $exception->getMessage());
        }

        $this->expectException(PermissionException::class);
        BlueprintAreas::sectionApi(
            'proxy-fixture',
            'proxysection',
            'write-post',
            $this->api('POST')
        );
    }

    /**
     * @param array{
     *   query?: array<string, mixed>,
     *   body?: array<string, mixed>,
     *   files?: array<string, mixed>
     * } $requestData
     */
    private function api(string $method, array $requestData = []): Api
    {
        return $this->kirby->api()->clone([
            'requestMethod' => $method,
            'requestData' => $requestData,
        ]);
    }

    private function impersonateReadOnlyUser(): User
    {
        $user = $this->kirby->user('proxy-readonly@kirby-blueprint-areas.test');
        if ($user === null) {
            $user = $this->kirby->users()->create([
                'email' => 'proxy-readonly@kirby-blueprint-areas.test',
                'name' => 'Proxy read-only',
                'role' => 'proxyreadonly',
                'password' => 'test-password',
            ]);
        }

        $this->kirby->impersonate($user->id());

        return $user;
    }
}
