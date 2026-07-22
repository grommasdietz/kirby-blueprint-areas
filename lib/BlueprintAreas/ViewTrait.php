<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\Api as KirbyApi;
use Kirby\Cms\Blueprint;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Exception\NotFoundException;
use Kirby\Panel\Ui\Buttons\LanguagesDropdown;
use Kirby\Panel\Ui\Buttons\OpenButton;
use Kirby\Panel\Ui\Buttons\PageStatusButton;
use Kirby\Panel\Ui\Buttons\PreviewButton;
use Kirby\Panel\Ui\Buttons\SettingsButton;
use Kirby\Panel\Ui\Buttons\VersionsButton;
use Kirby\Panel\Ui\Buttons\ViewButton;
use Kirby\Toolkit\Str;

trait ViewTrait
{
    public static function view(string $name): array
    {
        $context = static::resolveAreaContext($name, self::AREA_OPERATION_READ);
        $file = $context['file'];
        $bp = $context['props'];
        $model = $context['model'];
        $blueprint = $context['blueprint'];
        $layout = $context['layout'];

        $fields = static::collectFields($layout);
        $fieldNames = array_keys($fields);
        $latestStored = static::valuesFromContent(static::latestContent($model), $fieldNames);

        $form = static::formForBlueprint($layout, $latestStored, $model);
        $fieldProps = $form->toProps();
        $baselineValues = $form->toFormValues();
        $fieldSync = static::syncMapFromFields($fields);

        $currentValues = $baselineValues;
        $hasChanges = false;
        $changesContent = static::changesContent($model);
        if (is_array($changesContent)) {
            $changesStored = static::valuesFromContent($changesContent, $fieldNames);
            if (!empty($changesStored)) {
                $hasChanges = static::storedValuesDiffer($latestStored, $changesStored);
                $form->fill(array_replace($latestStored, $changesStored));
                $currentValues = $form->toFormValues();
            }
        }

        $modelPath = static::modelApiPath($model);

        return [
            'id' => $name,
            'title' => $blueprint->title(),
            'icon' => static::resolveIcon($name, $bp, $blueprint),
            'layout' => $layout,
            'fieldProps' => $fieldProps,
            'fieldSync' => $fieldSync,
            'values' => $currentValues,
            'baseline' => $baselineValues,
            'buttons' => static::viewButtons($blueprint, $model, static::viewId($name)),
            'meta' => array_merge(static::viewMeta($model), static::changesMeta($model), [
                'menuId' => static::menuId($name),
                'blueprintPath' => static::blueprintDisplayPath($file),
                'isEmpty' => static::blueprintIsEmpty($layout),
                'hasChanges' => $hasChanges,
                'modelPath' => $modelPath,
                'menuBadgeCount' => static::menuBadgeCount(),
                'canUpdate' => static::canUpdateModel($model),
            ]),
        ];
    }

    public static function save(string $name, array $values): array
    {
        $context = static::resolveAreaContext($name, self::AREA_OPERATION_UPDATE);
        $model = $context['model'];
        $layout = $context['layout'];
        $values = static::filterValuesForLayout($layout, $values);

        if ($values === []) {
            return static::view($name);
        }

        $fields = static::collectFields($layout);
        $latestStored = static::valuesFromContent(
            static::latestContent($model),
            array_keys($fields)
        );

        // Normalize and validate against the area's current model values, but
        // persist only keys that were actually submitted for this area request.
        $form = static::formForBlueprint($layout, $latestStored, $model);
        $form->submit($values, false);
        $form->validate();

        $stored = $form->toStoredValues();
        $updates = static::submittedStoredValues($stored, array_keys($values));

        if ($updates !== []) {
            $model = $model->update(
                static::storedValues($updates),
                static::languageCode()
            );
        }

        // A partial API publish must not discard unrelated pending fields from
        // the same area. The Panel submits the complete area and therefore still
        // clears the complete area scope during its regular publish flow.
        static::clearChangesForFields($model, $name, array_keys($values));

        return static::view($name);
    }

    public static function draft(string $name, array $values): array
    {
        $context = static::resolveAreaContext($name, self::AREA_OPERATION_UPDATE);
        $model = $context['model'];
        $layout = $context['layout'];
        $values = static::filterValuesForLayout($layout, $values);
        $fields = static::collectFields($layout);
        $fieldNames = array_keys($fields);
        $latestStored = static::valuesFromContent(static::latestContent($model), $fieldNames);

        // Build a baseline form so we can compare against normalized stored values.
        $baselineForm = static::formForBlueprint($layout, $latestStored, $model);
        $baselineStored = $baselineForm->toStoredValues();

        $form = static::formForBlueprint($layout, $latestStored, $model);
        $form = $form->submit($values, false);
        $stored = $form->toStoredValues();
        $changed = [];
        $unchanged = [];

        foreach (array_keys($values) as $fieldName) {
            $fieldName = Str::lower((string)$fieldName);
            if (!isset($fields[$fieldName]) || !array_key_exists($fieldName, $stored)) {
                continue;
            }

            $baselineValue = $baselineStored[$fieldName] ?? null;
            $currentValue = $stored[$fieldName];

            if (json_encode($baselineValue) === json_encode($currentValue)) {
                $unchanged[] = $fieldName;
            } else {
                $changed[$fieldName] = $currentValue;
            }
        }

        if (!empty($unchanged)) {
            static::clearChangesForFields($model, $name, $unchanged);
        }

        if (!empty($changed)) {
            static::updateChanges($model, static::storedValues($changed));
        }

        return ['success' => true];
    }

    public static function discard(string $name): array
    {
        $context = static::resolveAreaContext($name, self::AREA_OPERATION_UPDATE);
        $model = $context['model'];
        $layout = $context['layout'];
        $fieldNames = array_keys(static::collectFields($layout));
        static::clearChangesForFields($model, $name, $fieldNames);

        return static::view($name);
    }

    public static function fieldApi(
        string $name,
        string $fieldName,
        ?string $path,
        KirbyApi $api
    ): mixed {
        $context = static::resolveAreaContext($name, self::AREA_OPERATION_READ);
        $form = static::formForAreaContext($context, true);

        try {
            $field = $form->field(Str::lower($fieldName));
        } catch (NotFoundException $exception) {
            throw new NotFoundException(
                message: 'Field not found',
                previous: $exception
            );
        }

        return static::callProxyApi(
            $context,
            $field->api(),
            $path,
            $api,
            'field',
            $field
        );
    }

    public static function section(string $name, string $sectionName): array
    {
        $context = static::resolveAreaContext($name, self::AREA_OPERATION_READ);
        $section = $context['blueprint']->section($sectionName);
        if ($section === null) {
            throw new NotFoundException('Section not found');
        }

        $response = $section->toResponse();

        if (static::canUpdateModel($context['model']) === false) {
            return static::readonlySectionResponse($response);
        }

        return $response;
    }

    /**
     * Removes mutation controls from a section response for read-only areas.
     * The API proxy still enforces update permission independently.
     */
    private static function readonlySectionResponse(array $response): array
    {
        if (is_array($response['fields'] ?? null)) {
            foreach ($response['fields'] as &$field) {
                if (is_array($field)) {
                    $field['disabled'] = true;
                }
            }
            unset($field);
        }

        if (is_array($response['options'] ?? null)) {
            foreach ([
                'add',
                'batch',
                'create',
                'delete',
                'drag',
                'duplicate',
                'move',
                'replace',
                'sortable',
                'upload',
            ] as $option) {
                if (array_key_exists($option, $response['options'])) {
                    $response['options'][$option] = false;
                }
            }
        }

        if (is_array($response['data'] ?? null)) {
            foreach ($response['data'] as &$item) {
                if (!is_array($item) || !is_array($item['permissions'] ?? null)) {
                    continue;
                }

                foreach ([
                    'changeName',
                    'changeStatus',
                    'changeTemplate',
                    'changeTitle',
                    'create',
                    'delete',
                    'duplicate',
                    'move',
                    'replace',
                    'sort',
                    'update',
                    'upload',
                ] as $permission) {
                    if (array_key_exists($permission, $item['permissions'])) {
                        $item['permissions'][$permission] = false;
                    }
                }
            }
            unset($item);
        }

        return $response;
    }

    public static function sectionApi(
        string $name,
        string $sectionName,
        ?string $path,
        KirbyApi $api
    ): mixed {
        $context = static::resolveAreaContext($name, self::AREA_OPERATION_READ);
        $section = $context['blueprint']->section($sectionName);
        if ($section === null) {
            throw new NotFoundException('Section not found');
        }

        return static::callProxyApi(
            $context,
            $section->api() ?? [],
            $path,
            $api,
            'section',
            $section
        );
    }

    private static function viewMeta(ModelWithContent $model): array
    {
        $modified = null;
        if (method_exists($model, 'modified')) {
            /** @var object{modified: callable(): int|string|null} $model */
            $modified = $model->modified();
        }
        $lastSavedAt = null;
        if (is_int($modified)) {
            $lastSavedAt = date(DATE_ATOM, $modified);
        } elseif (is_string($modified) && $modified !== '') {
            $lastSavedAt = $modified;
        }

        $lastSavedBy = null;
        if (method_exists($model, 'modifiedBy')) {
            /** @var object{modifiedBy: callable(): mixed} $model */
            $by = $model->modifiedBy();
            if (is_object($by)) {
                $lastSavedBy = static::stringFromUser($by);
            } elseif (is_string($by) && $by !== '') {
                $lastSavedBy = $by;
            }
        }

        return [
            'lastSavedAt' => $lastSavedAt,
            'lastSavedBy' => $lastSavedBy,
        ];
    }

    private static function viewButtons(
        Blueprint $blueprint,
        ModelWithContent $model,
        string $viewId
    ): array {
        $buttons = $blueprint->buttons();

        if ($buttons === null) {
            return static::defaultLanguageButton($model);
        }

        if ($buttons === false) {
            return [];
        }

        if (is_array($buttons)) {
            if ($buttons === []) {
                return [];
            }

            $resolved = [];
            foreach ($buttons as $key => $button) {
                $resolvedButton = static::resolveButton(
                    $button,
                    is_string($key) ? $key : null,
                    $model,
                    $viewId
                );
                if ($resolvedButton !== null) {
                    $resolved[] = $resolvedButton;
                }
            }

            return $resolved;
        }

        return static::defaultLanguageButton($model);
    }

    private static function defaultLanguageButton(ModelWithContent $model): array
    {
        $button = static::languageButton($model);
        return $button === null ? [] : [$button];
    }

    private static function languageButton(ModelWithContent $model): array|null
    {
        if (!class_exists(LanguagesDropdown::class)) {
            return null;
        }

        $button = (new LanguagesDropdown($model))->render();
        return is_array($button) ? $button : null;
    }

    private static function resolveButton(
        array|bool|string|null $button,
        ?string $name,
        ModelWithContent $model,
        string $viewId
    ): array|null {
        if ($button === false) {
            return null;
        }

        if ($button === true) {
            $button = $name;
        }

        if (is_string($button)) {
            if ($builtin = static::builtinButton($button, $model)) {
                return $builtin;
            }

            $preferredView = static::modelViewId($model, $viewId);
            return ViewButton::factory(
                true,
                $name ?? $button,
                $preferredView,
                $model
            )?->render();
        }

        if (is_array($button)) {
            return ViewButton::factory($button, $name, $viewId, $model)?->render();
        }

        return null;
    }

    private static function builtinButton(string $name, ModelWithContent $model): array|null
    {
        return match (Str::lower($name)) {
            'open' => static::openButton($model),
            'preview' => static::previewButton($model),
            'settings' => static::settingsButton($model),
            'status' => static::statusButton($model),
            'versions' => static::versionsButton($model),
            'languages' => static::languageButton($model),
            default => null,
        };
    }

    private static function openButton(ModelWithContent $model): array|null
    {
        if ($model instanceof Page === false && $model instanceof Site === false) {
            return null;
        }

        $modelWithPreview = $model;

        $link = $modelWithPreview->previewUrl('latest');
        if ($link === null) {
            return null;
        }

        return static::ensureComponent(
            (new OpenButton($link))->render(),
            'k-open-view-button'
        );
    }

    private static function previewButton(ModelWithContent $model): array|null
    {
        if ($model instanceof Page === false && $model instanceof Site === false) {
            return null;
        }

        $modelWithPreview = $model;

        if ($modelWithPreview->previewUrl() === null) {
            return null;
        }

        $panel = $model->panel();
        $link = $panel->url(true) . '/preview/changes';
        return static::ensureComponent(
            (new PreviewButton($link))->render(),
            'k-preview-view-button'
        );
    }

    private static function settingsButton(ModelWithContent $model): array|null
    {
        return static::ensureComponent(
            (new SettingsButton($model))->render(),
            'k-settings-view-button'
        );
    }

    private static function statusButton(ModelWithContent $model): array|null
    {
        if ($model instanceof Page === false) {
            return null;
        }

        return static::ensureComponent(
            (new PageStatusButton($model))->render(),
            'k-status-view-button'
        );
    }

    private static function versionsButton(ModelWithContent $model): array|null
    {
        return static::ensureComponent(
            (new VersionsButton(model: $model))->render(),
            'k-versions-view-button'
        );
    }

    private static function modelViewId(ModelWithContent $model, string $default): string
    {
        if ($model instanceof Site) {
            return 'site';
        }

        if ($model instanceof Page) {
            return 'page';
        }

        return $default;
    }

    private static function ensureComponent(array|null $button, string $component): array|null
    {
        if ($button === null) {
            return null;
        }

        $button['component'] = $component;
        return $button;
    }

    private static function stringFromUser(object $user): ?string
    {
        // User::name() returns a Field object in Kirby 5
        if (method_exists($user, 'name')) {
            $name = $user->name();
            $nameString = static::stringFromValue($name);
            if ($nameString !== null && $nameString !== '') {
                return $nameString;
            }
        }

        if (method_exists($user, 'email')) {
            $email = $user->email();
            $emailString = static::stringFromValue($email);
            if ($emailString !== null && $emailString !== '') {
                return $emailString;
            }
        }

        return null;
    }

    private static function stringFromValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        // Handle Kirby Field objects which have a value() method
        if (is_object($value) && method_exists($value, 'value')) {
            $value = $value->value();
            return is_string($value) ? $value : null;
        }

        return null;
    }
}
