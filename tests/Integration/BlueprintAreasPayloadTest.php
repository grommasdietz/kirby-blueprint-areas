<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use GrommasDietz\Areas\Tests\TestCase;
use Kirby\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

final class BlueprintAreasPayloadTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $contentSnapshots = [];

    protected function setUp(): void
    {
        parent::setUp();
        $root = realpath(__DIR__ . '/../../playground/content');
        if ($root !== false) {
            foreach (['de', 'en'] as $language) {
                $path = $root . '/site.' . $language . '.txt';
                $content = is_file($path) ? file_get_contents($path) : null;
                $this->contentSnapshots[$path] = $content === false ? null : $content;
            }
        }
        $this->bootKirby()->impersonate('kirby');
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

    public function testCanonicalPayloadAcceptsLanguageMetadata(): void
    {
        $this->assertSame(
            ['text' => 'Value'],
            BlueprintAreas::requestValues([
                'values' => ['text' => 'Value'],
                'language' => 'de',
            ])
        );
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsMalformedPayloads(mixed $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        BlueprintAreas::requestValues($payload);
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'scalar payload' => ['value'];
        yield 'non-array values' => [['values' => 'value']];
        yield 'list values' => [['values' => ['first', 'second']]];
        yield 'unknown top-level key' => [[
            'values' => ['text' => 'Value'],
            'csrf' => 'unexpected',
        ]];
    }

    public function testEmptyCanonicalValuesObjectIsAccepted(): void
    {
        $this->assertSame([], BlueprintAreas::requestValues(['values' => []]));
    }

    public function testPayloadDepthBoundary(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.blueprint-areas' => [
                    'api' => ['maxPayloadDepth' => 2],
                ],
            ],
        ])->impersonate('kirby');

        $valid = ['field' => ['nested' => 'value']];
        $this->assertSame($valid, BlueprintAreas::requestValues(['values' => $valid]));

        $this->expectException(InvalidArgumentException::class);
        BlueprintAreas::requestValues([
            'values' => ['field' => ['nested' => ['too' => 'deep']]],
        ]);
    }

    public function testPayloadByteBoundary(): void
    {
        $values = ['text' => str_repeat('x', 32)];
        $bytes = strlen((string)json_encode($values));

        $this->bootKirby([
            'options' => [
                'grommasdietz.blueprint-areas' => [
                    'api' => ['maxPayloadBytes' => $bytes],
                ],
            ],
        ])->impersonate('kirby');

        $this->assertSame($values, BlueprintAreas::requestValues(['values' => $values]));

        $this->expectException(InvalidArgumentException::class);
        BlueprintAreas::requestValues([
            'values' => ['text' => str_repeat('x', 33)],
        ]);
    }

    #[DataProvider('disabledLimitProvider')]
    public function testInvalidOrDisabledPayloadLimitsPreserveCompatibility(mixed $depth, mixed $bytes): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.blueprint-areas' => [
                    'api' => [
                        'maxPayloadDepth' => $depth,
                        'maxPayloadBytes' => $bytes,
                    ],
                ],
            ],
        ])->impersonate('kirby');

        $values = [
            'field' => ['deep' => ['nested' => ['value' => str_repeat('x', 128)]]],
        ];
        $this->assertSame($values, BlueprintAreas::requestValues(['values' => $values]));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: mixed}>
     */
    public static function disabledLimitProvider(): iterable
    {
        yield 'zero' => [0, 0];
        yield 'negative' => [-1, -1];
        yield 'non-integer' => ['2', '64'];
        yield 'null' => [null, null];
    }

    public function testSaveAndDraftOnlyAcceptKnownSubmittableFields(): void
    {
        $this->bootKirby([
            'testBlueprints' => [
                'areas/payload-filter.yml' => <<<'YAML'
title: Payload filter

fields:
  accepted:
    type: text
  untouched:
    type: text
  disabled_field:
    type: text
    disabled: true
YAML,
            ],
        ])->impersonate('kirby');

        try {
            BlueprintAreas::discard('payload-filter');
            BlueprintAreas::draft('payload-filter', [
                'ACCEPTED' => 'Stored draft',
                'untouched' => 'Pending untouched',
                'disabled_field' => 'Must not be stored',
                'unknown_field' => 'Must not pass through',
            ]);

            $language = $this->kirby->language()?->code() ?? 'default';
            $changes = $this->kirby->site()->version('changes')->read($language);
            if (!is_array($changes)) {
                $this->fail('The changes version must exist after drafting an area value.');
            }

            $this->assertSame('Stored draft', $changes['accepted'] ?? null);
            $this->assertSame('Pending untouched', $changes['untouched'] ?? null);
            $this->assertArrayNotHasKey('disabled_field', $changes);
            $this->assertArrayNotHasKey('unknown_field', $changes);

            BlueprintAreas::save('payload-filter', [
                'ACCEPTED' => 'Stored latest',
                'disabled_field' => 'Must not be stored',
                'unknown_field' => 'Must not pass through',
            ]);

            $latest = $this->kirby->site()->content()->toArray();
            $this->assertSame('Stored latest', $latest['accepted'] ?? null);
            $this->assertArrayNotHasKey('untouched', $latest);
            $this->assertArrayNotHasKey('disabled_field', $latest);
            $this->assertArrayNotHasKey('unknown_field', $latest);

            $remainingChanges = $this->kirby->site()->version('changes')->read($language);
            if (!is_array($remainingChanges)) {
                $this->fail('Publishing one field must preserve unrelated area changes.');
            }
            $this->assertArrayNotHasKey('accepted', $remainingChanges);
            $this->assertSame('Pending untouched', $remainingChanges['untouched'] ?? null);
        } finally {
            try {
                BlueprintAreas::discard('payload-filter');
            } catch (\Throwable) {
            }
        }
    }
}
