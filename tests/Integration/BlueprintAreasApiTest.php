<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use GrommasDietz\Areas\Tests\TestCase;
use Kirby\Exception\AuthException;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

final class BlueprintAreasApiTest extends TestCase
{
    private const API_ROOT = 'grommasdietz/blueprint-areas';

    /** @var array<string, string|null> */
    private array $contentSnapshots = [];
    /** @var array<string, mixed> */
    private array $apiBody = [];
    /** @var array<string, mixed> */
    private array $apiQuery = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootApi([
            'values' => ['text' => 'API value'],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->contentSnapshots as $path => $content) {
            if ($content === null) {
                @unlink($path);
            } else {
                file_put_contents($path, $content);
            }
        }

        parent::tearDown();
    }

    public function testApiAuthenticationIsRequired(): void
    {
        $this->kirby->impersonate(null);

        $this->expectException(AuthException::class);
        $this->kirby->api()->call(self::API_ROOT . '/blueprints', 'GET');
    }

    public function testAuthenticatedBlueprintListUsesKirbyApiRouter(): void
    {
        $response = $this->kirby->api()->call(self::API_ROOT . '/blueprints', 'GET');

        $this->assertIsArray($response);
        $this->assertContains('fields', array_column($response, 'id'));
    }

    #[DataProvider('publishAliasProvider')]
    public function testPublishAliasesPersistCanonicalPayload(string $suffix): void
    {
        $this->callApi($this->areaPath('fields', $suffix));

        $this->assertSame('API value', $this->kirby->site()->content()->get('text')->value());
    }

    /** @return iterable<string, array{0: string}> */
    public static function publishAliasProvider(): iterable
    {
        yield 'base post' => [''];
        yield 'publish alias' => ['/publish'];
    }

    #[DataProvider('draftAliasProvider')]
    public function testDraftAliasesPersistOnlyToChanges(string $suffix): void
    {
        BlueprintAreas::discard('fields');
        $latest = $this->kirby->site()->content()->get('text')->value();

        $this->callApi($this->areaPath('fields', $suffix));

        $language = $this->kirby->language()?->code() ?? 'default';
        $changes = $this->kirby->site()->version('changes')->read($language);
        if (!is_array($changes)) {
            $this->fail('The API draft alias must create a changes version.');
        }
        $this->assertSame('API value', $changes['text'] ?? null);
        $this->assertSame($latest, $this->kirby->site()->content()->get('text')->value());
    }

    /** @return iterable<string, array{0: string}> */
    public static function draftAliasProvider(): iterable
    {
        yield 'save alias' => ['/save'];
    }

    #[DataProvider('discardAliasProvider')]
    public function testDiscardAliasesRemoveAreaChanges(string $suffix): void
    {
        BlueprintAreas::draft('fields', ['text' => 'Temporary API draft']);

        $this->callApi($this->areaPath('fields', $suffix));

        $language = $this->kirby->language()?->code() ?? 'default';
        $this->assertFalse($this->kirby->site()->version('changes')->exists($language));
    }

    /** @return iterable<string, array{0: string}> */
    public static function discardAliasProvider(): iterable
    {
        yield 'discard alias' => ['/discard'];
    }

    #[DataProvider('knownWrongMethodProvider')]
    public function testKnownResourcesReturn405ForWrongMethods(string $path, string $method): void
    {
        try {
            $this->kirby->api()->call($path, $method);
            $this->fail('Known API resource must return method not allowed.');
        } catch (KirbyException $exception) {
            $this->assertSame(405, $exception->getHttpCode());
        }
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function knownWrongMethodProvider(): iterable
    {
        yield 'list post' => [self::API_ROOT . '/blueprints', 'POST'];
        yield 'area put' => [self::API_ROOT . '/blueprints/fields', 'PUT'];
        yield 'save get' => [self::API_ROOT . '/blueprints/fields/save', 'GET'];
        yield 'publish delete' => [self::API_ROOT . '/blueprints/fields/publish', 'DELETE'];
        yield 'discard patch' => [self::API_ROOT . '/blueprints/fields/discard', 'PATCH'];
        yield 'changes summary post' => [self::API_ROOT . '/changes', 'POST'];
    }

    #[DataProvider('unknownResourceProvider')]
    public function testUnknownResourcesReturn404(string $path): void
    {
        try {
            $this->kirby->api()->call($path, 'GET');
            $this->fail('Unknown API resource must return not found.');
        } catch (Throwable $exception) {
            $httpCode = method_exists($exception, 'getHttpCode')
                ? $exception->getHttpCode()
                : $exception->getCode();
            $this->assertSame(404, $httpCode);
        }
    }

    /** @return iterable<string, array{0: string}> */
    public static function unknownResourceProvider(): iterable
    {
        yield 'area' => [self::API_ROOT . '/blueprints/missing'];
        yield 'field' => [self::API_ROOT . '/blueprints/fields/fields/missing/uuid'];
        yield 'section' => [self::API_ROOT . '/blueprints/fields/sections/missing'];
    }

    public function testMalformedCanonicalPayloadReturns400(): void
    {
        $this->bootApi([
            'values' => 'invalid',
        ]);

        try {
            $this->callApi($this->areaPath('fields'));
            $this->fail('Malformed values payload must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(400, $exception->getHttpCode());
        }
    }

    public function testApiLanguageHeaderSelectsTheWrittenLanguage(): void
    {
        $this->bootApi([
            'values' => ['text' => 'Deutsch über Header'],
        ]);

        $this->callApi(
            $this->areaPath('fields'),
            ['headers' => ['x-language' => 'de']]
        );

        $this->assertSame(
            'Deutsch über Header',
            $this->kirby->site()->content('de')->get('text')->value()
        );
    }

    public function testApiLanguageQuerySelectsTheWrittenLanguage(): void
    {
        $this->bootApi(
            ['values' => ['text' => 'Deutsch über API']],
            ['language' => 'de']
        );

        $this->callApi(
            $this->areaPath('fields'),
            ['query' => ['language' => 'de']]
        );

        $this->assertSame(
            'Deutsch über API',
            $this->kirby->site()->content('de')->get('text')->value()
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private function bootApi(array $body, array $query = []): void
    {
        $this->snapshotContent();
        $this->apiBody = $body;
        $this->apiQuery = $query;

        $this->bootKirby([
            'request' => [
                'method' => 'POST',
                'body' => [],
                'query' => [],
                'url' => 'http://example.test/api/' . self::API_ROOT,
                'cli' => false,
            ],
            'options' => [
                'api.allowImpersonation' => true,
            ],
        ])->impersonate('kirby');
    }

    private function areaPath(string $area, string $suffix = ''): string
    {
        return self::API_ROOT . '/blueprints/' . $area . $suffix;
    }

    /**
     * Calls the Kirby API with the same normalized request data that an HTTP
     * request provides to plugin API routes.
     *
     * @param array<string, mixed> $requestData
     */
    private function callApi(string $path, array $requestData = []): mixed
    {
        return $this->kirby->api()->call($path, 'POST', [
            'body' => $requestData['body'] ?? $this->apiBody,
            'query' => $requestData['query'] ?? $this->apiQuery,
            'headers' => $requestData['headers'] ?? [],
            'files' => $requestData['files'] ?? [],
        ]);
    }

    private function snapshotContent(): void
    {
        $root = realpath(__DIR__ . '/../../playground/content');
        if ($root === false) {
            return;
        }

        foreach (['de', 'en'] as $language) {
            $path = $root . '/site.' . $language . '.txt';
            if (array_key_exists($path, $this->contentSnapshots)) {
                continue;
            }

            $content = is_file($path) ? file_get_contents($path) : null;
            $this->contentSnapshots[$path] = $content === false ? null : $content;
        }
    }
}
