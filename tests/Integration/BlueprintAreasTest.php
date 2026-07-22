<?php

declare(strict_types=1);

namespace GrommasDietz\Areas\Tests\Integration;

use GrommasDietz\Areas\BlueprintAreas;
use Kirby\Cms\Permissions;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use Kirby\Toolkit\I18n;
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

    public function testRegistrationListDoesNotWarmTranslationCache(): void
    {
        I18n::$translations = [];

        BlueprintAreas::listForRegistration();

        $this->assertSame([], I18n::translations());
    }

    public function testRegistrationListPreservesOtherPluginTranslations(): void
    {
        $this->kirby->extend([
            'translations' => [
                'en' => [
                    'test.plugin.translation' => 'Other plugin translation',
                ],
            ],
        ]);

        I18n::$translations = [];

        BlueprintAreas::listForRegistration();

        $this->assertSame('Other plugin translation', I18n::translate('test.plugin.translation'));
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

    public function testNativeAreaAccessPermissionBlocksArea(): void
    {
        $rolePath = $this->kirby->root('blueprints') . '/users/nativeaccess.yml';

        file_put_contents($rolePath, <<<'YAML'
title: Native Access

permissions:
  access:
    fields: false
YAML);

        $user = $this->kirby->users()->create([
            'email' => 'native-access-' . uniqid() . '@kirby-blueprint-areas.test',
            'name' => 'Native Access User',
            'role' => 'nativeaccess',
            'password' => 'test-password',
        ]);

        try {
            $this->kirby->impersonate($user->id());

            $list = BlueprintAreas::list();
            $ids = array_map(static fn (array $i): string => (string)$i['id'], $list);

            $this->assertNotContains('fields', $ids);

            $this->expectException(PermissionException::class);
            BlueprintAreas::view('fields');
        } finally {
            $this->kirby->impersonate('kirby');
            $user->delete();
            @unlink($rolePath);
        }
    }

    public function testLegacyAreaPermissionsAreRegisteredForDiscoveredBlueprints(): void
    {
        $this->assertSame(true, Permissions::$extendedActions['areas']['home'] ?? null);
        $this->assertSame(true, Permissions::$extendedActions['areas']['fields'] ?? null);

        $role = $this->kirby->roles()->find('editor');
        $this->assertNotNull($role);
        $this->assertSame(true, $role->permissions()->for('areas', 'fields', false));
        $this->assertSame(false, $role->permissions()->for('areas', 'empty', true));
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

    public function testTranslationAreaLoadsValuesPerContentLanguage(): void
    {
        $this->kirby->setCurrentLanguage('en');

        try {
            $english = BlueprintAreas::view('translations');

            $this->assertSame(
                'This value is stored in English.',
                $english['values']['translatedcontent'] ?? null
            );
            $noteText = $english['fieldProps']['note']['text'] ?? null;
            $this->assertIsString($noteText);
            $this->assertStringContainsString(
                'Switch the content language in the header.',
                $noteText
            );

            $this->kirby->setCurrentLanguage('de');
            $german = BlueprintAreas::view('translations');

            $this->assertSame(
                'Dieser Wert ist auf Deutsch gespeichert.',
                $german['values']['translatedcontent'] ?? null
            );
        } finally {
            $this->kirby->setCurrentLanguage('en');
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

    public function testViewIncludesSanitizedBlueprintPath(): void
    {
        $view = BlueprintAreas::view('fields');
        $blueprintPath = $view['meta']['blueprintPath'] ?? null;

        $this->assertSame('/areas/fields.yml', $blueprintPath);
        $this->assertStringNotContainsString(
            str_replace('\\', '/', $this->kirby->root('blueprints')),
            (string)$blueprintPath
        );
    }

    public function testCanonicalAndLegacyRequestPayloadsRemainSupported(): void
    {
        $this->assertSame(
            ['text' => 'Canonical'],
            BlueprintAreas::requestValues(['values' => ['text' => 'Canonical']])
        );

        $this->assertSame(
            ['text' => 'Legacy'],
            BlueprintAreas::requestValues(['text' => 'Legacy'])
        );
    }

    public function testRejectsMalformedCanonicalRequestPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BlueprintAreas::requestValues([
            'values' => ['text' => 'Value'],
            'unexpected' => true,
        ]);
    }

    public function testSiteAccessPermissionBlocksAreaData(): void
    {
        $rolePath = $this->kirby->root('blueprints') . '/users/siteblocked.yml';

        file_put_contents($rolePath, <<<'YAML'
title: Site blocked

permissions:
  access:
    fields: true
    site: false
  site:
    access: false
    update: false
YAML);

        $user = $this->kirby->users()->create([
            'email' => 'site-blocked-' . uniqid() . '@kirby-blueprint-areas.test',
            'name' => 'Site blocked user',
            'role' => 'siteblocked',
            'password' => 'test-password',
        ]);

        try {
            $this->kirby->impersonate($user->id());

            $ids = array_column(BlueprintAreas::list(), 'id');
            $this->assertNotContains('fields', $ids);

            try {
                BlueprintAreas::view('fields');
                $this->fail('Site access denial must block the area view.');
            } catch (PermissionException $exception) {
                $this->assertSame('Not allowed', $exception->getMessage());
            }
        } finally {
            $this->kirby->impersonate('kirby');
            $user->delete();
            @unlink($rolePath);
        }
    }

    public function testPageReadPermissionBlocksQueriedAreaData(): void
    {
        $blueprintPath = $this->kirby->root('blueprints') . '/areas/page-read-blocked.yml';
        $rolePath = $this->kirby->root('blueprints') . '/users/pagereadblocked.yml';

        file_put_contents($blueprintPath, <<<'YAML'
title: Page read blocked
query: site.find("home")

options:
  access:
    pagereadblocked: true
    "*": false

fields:
  subtitle:
    label: Subtitle
    type: text
YAML);

        file_put_contents($rolePath, <<<'YAML'
title: Page read blocked

permissions:
  access:
    page-read-blocked: true
  pages:
    access: true
    read: false
    update: false
YAML);

        $user = $this->kirby->users()->create([
            'email' => 'page-read-blocked-' . uniqid() . '@kirby-blueprint-areas.test',
            'name' => 'Page read blocked user',
            'role' => 'pagereadblocked',
            'password' => 'test-password',
        ]);

        try {
            $this->kirby->impersonate($user->id());

            $ids = array_column(BlueprintAreas::list(), 'id');
            $this->assertNotContains('page-read-blocked', $ids);

            try {
                BlueprintAreas::view('page-read-blocked');
                $this->fail('Page read denial must block the queried area view.');
            } catch (PermissionException $exception) {
                $this->assertSame('Not allowed', $exception->getMessage());
            }
        } finally {
            $this->kirby->impersonate('kirby');
            $user->delete();
            @unlink($blueprintPath);
            @unlink($rolePath);
        }
    }

    public function testReadOnlyRoleCanUseReadApisButCannotMutate(): void
    {
        $rolePath = $this->kirby->root('blueprints') . '/users/readonlyareas.yml';

        file_put_contents($rolePath, <<<'YAML'
title: Read-only areas

permissions:
  access:
    fields: true
  site:
    access: true
    update: false
YAML);

        $user = $this->kirby->users()->create([
            'email' => 'read-only-' . uniqid() . '@kirby-blueprint-areas.test',
            'name' => 'Read-only user',
            'role' => 'readonlyareas',
            'password' => 'test-password',
        ]);

        try {
            $this->kirby->impersonate($user->id());

            $view = BlueprintAreas::view('fields');
            $this->assertSame('fields', $view['id']);
            $this->assertFalse($view['meta']['canUpdate'] ?? true);

            $section = BlueprintAreas::section('fields', 'textfields');
            $this->assertTrue($section['fields']['text']['disabled'] ?? false);

            $readApi = $this->kirby->api()->clone([
                'requestMethod' => 'GET',
            ]);
            $uuid = BlueprintAreas::fieldApi('fields', 'blocks', 'uuid', $readApi);
            $this->assertArrayHasKey('uuid', $uuid);

            try {
                BlueprintAreas::save('fields', ['text' => 'Denied']);
                $this->fail('A read-only user must not save area values.');
            } catch (PermissionException $exception) {
                $this->assertSame('Not allowed', $exception->getMessage());
            }

            $writeApi = $this->kirby->api()->clone([
                'requestMethod' => 'POST',
                'requestData' => [
                    'body' => ['html' => '<p>Denied</p>'],
                ],
            ]);

            try {
                BlueprintAreas::fieldApi('fields', 'blocks', 'paste', $writeApi);
                $this->fail('A read-only user must not call mutating field routes.');
            } catch (PermissionException $exception) {
                $this->assertSame('Not allowed', $exception->getMessage());
            }
        } finally {
            $this->kirby->impersonate('kirby');
            $user->delete();
            @unlink($rolePath);
        }
    }

    public function testBooleanRoleAccessDenialBlocksCustomAreas(): void
    {
        $rolePath = $this->kirby->root('blueprints') . '/users/allaccessblocked.yml';

        file_put_contents($rolePath, <<<'YAML'
title: All access blocked

permissions:
  access: false
YAML);

        $user = $this->kirby->users()->create([
            'email' => 'all-access-blocked-' . uniqid() . '@kirby-blueprint-areas.test',
            'name' => 'All access blocked user',
            'role' => 'allaccessblocked',
            'password' => 'test-password',
        ]);

        try {
            $this->kirby->impersonate($user->id());

            $this->assertNotContains('fields', array_column(BlueprintAreas::list(), 'id'));

            $this->expectException(PermissionException::class);
            BlueprintAreas::view('fields');
        } finally {
            $this->kirby->impersonate('kirby');
            $user->delete();
            @unlink($rolePath);
        }
    }

    public function testHeadFieldApiUsesReadAuthorization(): void
    {
        $api = $this->kirby->api()->clone([
            'requestMethod' => 'HEAD',
        ]);

        $result = BlueprintAreas::fieldApi('fields', 'blocks', 'uuid', $api);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uuid', $result);
    }

    public function testBooleanAreaAccessDenialIsEnforced(): void
    {
        $blueprintPath = $this->kirby->root('blueprints') . '/areas/boolean-denied.yml';

        file_put_contents($blueprintPath, <<<'YAML'
title: Boolean denied

options:
  access: false

fields:
  note:
    type: text
YAML);

        try {
            $this->assertNotContains('boolean-denied', array_column(BlueprintAreas::list(), 'id'));

            $this->expectException(PermissionException::class);
            BlueprintAreas::view('boolean-denied');
        } finally {
            @unlink($blueprintPath);
        }
    }

    public function testRejectsAreaPathTraversal(): void
    {
        $this->expectException(NotFoundException::class);

        BlueprintAreas::view('../fields');
    }

    public function testProxyRejectsKnownPathWithWrongMethod(): void
    {
        $api = $this->kirby->api()->clone([
            'requestMethod' => 'GET',
        ]);

        try {
            BlueprintAreas::fieldApi('fields', 'blocks', 'paste', $api);
            $this->fail('The field API proxy must reject a known path with the wrong method.');
        } catch (KirbyException $exception) {
            $this->assertSame(
                'Method not allowed for field or section API route',
                $exception->getMessage()
            );
        }
    }

}
