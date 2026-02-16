<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use GrommasDietz\Areas\Tests\TestCase;

final class BlueprintAreasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby()->impersonate('kirby');
    }

    public function testListsBlueprintsFromPlayground(): void
    {
        $list = BlueprintAreas::list();

        $ids = array_map(static fn (array $i): string => (string)$i['id'], $list);
        sort($ids);

        $this->assertSame(['buttons', 'empty', 'fields', 'home', 'translations'], $ids);
    }

    public function testListRespectsAccessRules(): void
    {
        $list = BlueprintAreas::list();
        $ids = array_map(static fn (array $i): string => (string)$i['id'], $list);

        $this->assertContains('buttons', $ids);

        $editor = $this->kirby->users()->create([
            'email' => 'editor-' . uniqid() . '@kirby-blueprint-areas.test',
            'name' => 'Editor User',
            'role' => 'editor',
            'password' => 'test-password',
        ]);

        try {
            $this->kirby->impersonate($editor->id());

            $editorList = BlueprintAreas::list();
            $editorIds = array_map(static fn (array $i): string => (string)$i['id'], $editorList);

            $this->assertContains('fields', $editorIds);
            $this->assertNotContains('buttons', $editorIds);
            $this->assertNotContains('home', $editorIds);
        } finally {
            $this->kirby->impersonate('kirby');
            $editor->delete();
        }
    }

    public function testRestrictedAreaBlocksEditor(): void
    {
        $editor = $this->kirby->users()->create([
            'email' => 'editor-restricted-' . uniqid() . '@kirby-blueprint-areas.test',
            'name' => 'Editor User',
            'role' => 'editor',
            'password' => 'test-password',
        ]);

        try {
            $this->kirby->impersonate($editor->id());

            $this->expectException(PermissionException::class);
            BlueprintAreas::view('buttons');
        } finally {
            $this->kirby->impersonate('kirby');
            $editor->delete();
        }
    }

    public function testAreaAccessCannotOverrideRoleDenial(): void
    {
        $blueprintsRoot = $this->kirby->root('blueprints') . '/areas';
        $blueprintPath = $blueprintsRoot . '/access-override.yml';

        file_put_contents($blueprintPath, <<<'YAML'
title: Access Override

options:
  access:
    editor: true
    "*": true

fields:
  note:
    label: Note
    type: text
YAML);

        $editor = $this->kirby->users()->create([
            'email' => 'editor-override-' . uniqid() . '@kirby-blueprint-areas.test',
            'name' => 'Editor User',
            'role' => 'editor',
            'password' => 'test-password',
        ]);

        try {
            $this->kirby->impersonate($editor->id());

            $editorList = BlueprintAreas::list();
            $editorIds = array_map(static fn (array $i): string => (string)$i['id'], $editorList);

            $this->assertNotContains('access-override', $editorIds);

            $this->expectException(PermissionException::class);
            BlueprintAreas::view('access-override');
        } finally {
            $this->kirby->impersonate('kirby');
            $editor->delete();
            @unlink($blueprintPath);
        }
    }

    public function testAreaAccessCanDisableAdmin(): void
    {
        $blueprintsRoot = $this->kirby->root('blueprints') . '/areas';
        $blueprintPath = $blueprintsRoot . '/admin-denied.yml';

        file_put_contents($blueprintPath, <<<'YAML'
title: Admin Denied

options:
  access:
    admin: false
    "*": true

fields:
  note:
    label: Note
    type: text
YAML);

        try {
            $this->kirby->impersonate('kirby');

            $list = BlueprintAreas::list();
            $ids = array_map(static fn (array $i): string => (string)$i['id'], $list);

            $this->assertNotContains('admin-denied', $ids);
        } finally {
            @unlink($blueprintPath);
        }
    }

    public function testListUsesTranslatedBlueprintTitles(): void
    {
        $titles = array_column(BlueprintAreas::list(), 'title', 'id');
        $this->assertSame('Translations', $titles['translations'] ?? null);

        $this->kirby->setCurrentTranslation('de');
        try {
            $translated = array_column(BlueprintAreas::list(), 'title', 'id');
            $this->assertSame('Übersetzungen', $translated['translations'] ?? null);
        } finally {
            $this->kirby->setCurrentTranslation('en');
        }
    }

    public function testLoadsBlueprintViewPayload(): void
    {
        $view = BlueprintAreas::view('fields');

        $this->assertSame('fields', $view['id']);
        $this->assertArrayHasKey('layout', $view);
        $this->assertArrayHasKey('fieldProps', $view);
        $this->assertArrayHasKey('values', $view);

        // seeded in playground/content/site.txt
        $this->assertArrayHasKey('text', $view['values']);
    }

    public function testBlueprintButtonsResolveList(): void
    {
        $view = BlueprintAreas::view('buttons');

        $components = array_column($view['buttons'], 'component');

        $this->assertSame(
            ['k-preview-view-button', 'k-open-view-button'],
            $components
        );
    }

    public function testSavesValuesToSite(): void
    {
        BlueprintAreas::save('fields', [
            'text' => 'My Test Site',
        ]);

        $content = $this->kirby->site()->content()->toArray();

        $this->assertSame('My Test Site', $content['text'] ?? null);
    }

    public function testSavesValuesToQueriedModel(): void
    {
        $page = $this->kirby->page('home');
        $this->assertNotNull($page);

        $defaultLanguage = $this->kirby->languages()->default()->code();
        $contentFile = $page->version('latest')->contentFile($defaultLanguage);
        $original = is_file($contentFile) ? file_get_contents($contentFile) : null;
        if ($original === false) {
            $original = null;
        }

        try {
            BlueprintAreas::save('home', [
                'subtitle' => 'Subtitle',
            ]);

            $page = $this->kirby->page('home');
            $this->assertSame('Subtitle', $page?->content()->get('subtitle')->value());
        } finally {
            if ($original !== null) {
                file_put_contents($contentFile, $original);
            }
        }
    }

    public function testDraftAndDiscardChanges(): void
    {
        BlueprintAreas::discard('fields');

        BlueprintAreas::draft('fields', [
            'text' => 'Drafted value',
        ]);

        $summary = BlueprintAreas::changesSummary();
        $areaCounts = [];
        foreach ($summary['areas'] ?? [] as $area) {
            $areaCounts[$area['id']] = $area['count'];
        }

        $this->assertSame(1, $areaCounts[BlueprintAreas::menuId('fields')] ?? null);

        BlueprintAreas::discard('fields');

        $summary = BlueprintAreas::changesSummary();
        $areaCounts = [];
        foreach ($summary['areas'] ?? [] as $area) {
            $areaCounts[$area['id']] = $area['count'];
        }

        $this->assertSame(0, $areaCounts[BlueprintAreas::menuId('fields')] ?? null);
    }

    public function testDraftStoresValuesInChangesVersion(): void
    {
        $model = $this->kirby->site();
        if (method_exists($model, 'version') === false) {
            $this->markTestSkipped('Kirby versions are not available.');
        }

        BlueprintAreas::discard('fields');
        BlueprintAreas::draft('fields', [
            'text' => 'Drafted value',
        ]);

        $changes = $model->version('changes');
        if ($changes === null) {
            $this->markTestSkipped('Changes version is not available.');
        }

        $this->assertSame('Drafted value', $changes->content()->get('text')->value());
    }

    public function testDraftDoesNotCreateChangesForEmptyMissingField(): void
    {
        $model = $this->kirby->site();
        if (method_exists($model, 'version') === false) {
            $this->markTestSkipped('Kirby versions are not available.');
        }

        $language = $this->kirby->language()?->code() ?? 'default';

        // Ensure a clean slate (the playground ships with a `_changes` fixture file).
        BlueprintAreas::discard('fields');

        $changes = $model->version('changes');
        if ($changes !== null && method_exists($changes, 'exists') === true && $changes->exists($language) === true) {
            $changes->delete($language);
        }

        $blueprintsRoot = $this->kirby->root('blueprints') . '/areas';
        $blueprintPath = $blueprintsRoot . '/missing-field.yml';

        file_put_contents($blueprintPath, <<<'YAML'
title: Missing Field

fields:
  missing_field:
    label: Missing field
    type: text
YAML);

        try {
            // Drafting an empty value for a field that doesn't exist in the model yet
            // should not create a changes version (and therefore no lock).
            BlueprintAreas::draft('missing-field', [
                'missing_field' => '',
            ]);

            $changes = $model->version('changes');
            if ($changes === null || method_exists($changes, 'exists') === false) {
                $this->markTestSkipped('Changes version is not available.');
            }

            $this->assertFalse($changes->exists($language));
        } finally {
            @unlink($blueprintPath);
        }
    }

    public function testStoresValuesPerLanguage(): void
    {
        $this->kirby->setCurrentLanguage('de');
        BlueprintAreas::save('fields', [
            'text' => 'Deutsch',
        ]);

        $this->assertSame(
            'Deutsch',
            $this->kirby->site()->content('de')->get('text')->value()
        );

        $this->kirby->setCurrentLanguage('en');
        BlueprintAreas::save('fields', [
            'text' => 'English',
        ]);

        $this->assertSame(
            'English',
            $this->kirby->site()->content('en')->get('text')->value()
        );
    }

    public function testReturnsCoreSectionResponse(): void
    {
        $response = BlueprintAreas::section('fields', 'pages');

        $this->assertSame('ok', $response['status'] ?? null);
        $this->assertSame('pages', $response['name'] ?? null);
        $this->assertSame('pages', $response['type'] ?? null);
    }

    public function testRejectsInvalidQuery(): void
    {
        $blueprintsRoot = $this->kirby->root('blueprints') . '/areas';
        $invalidBlueprint = $blueprintsRoot . '/invalid.yml';

        file_put_contents($invalidBlueprint, <<<'YAML'
title: Invalid
query: site.children

sections:
  main:
    type: fields
    fields:
      text:
        label: Text
        type: text
YAML);

        try {
            $this->expectException(NotFoundException::class);
            BlueprintAreas::view('invalid');
        } finally {
            @unlink($invalidBlueprint);
        }
    }

    public function testEmptyAreaFlags(): void
    {
        $view = BlueprintAreas::view('empty');

        $this->assertSame(true, $view['meta']['isEmpty'] ?? null);
        $this->assertIsArray($view['layout']['tabs'] ?? null);
    }

    public function testMissingBlueprintThrows(): void
    {
        $this->expectException(NotFoundException::class);

        BlueprintAreas::view('missing');
    }

    public function testFieldApiReturnsUuid(): void
    {
        $api = $this->kirby->api()->clone([
            'requestMethod' => 'GET',
        ]);

        $result = BlueprintAreas::fieldApi('fields', 'blocks', 'uuid', $api);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uuid', $result);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f\\-]{36}$/i',
            (string)$result['uuid']
        );
    }

    public function testSectionApiMissingSectionThrows(): void
    {
        $this->expectException(NotFoundException::class);

        $api = $this->kirby->api();
        BlueprintAreas::sectionApi('fields', 'missing', null, $api);
    }

    public function testChangesLockForAreaReturnsArrayOrNull(): void
    {
        $lock = BlueprintAreas::changesLockForArea('fields');

        $this->assertTrue(is_array($lock) || $lock === null);
    }

    public function testViewIncludesBlueprintPath(): void
    {
        $view = BlueprintAreas::view('fields');

        $this->assertIsString($view['meta']['blueprintPath'] ?? null);
        $this->assertStringContainsString('site/blueprints/areas', $view['meta']['blueprintPath']);
    }
}
