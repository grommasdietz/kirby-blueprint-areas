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
        $file = static::blueprintFile($name);
        if ($file === null) {
            throw new NotFoundException('Blueprint not found');
        }

        $bp = static::readBlueprint($file);
        $bp['name'] = $name;
        $model = static::modelForArea($name, $bp);
        static::requireAreaAccess($model, $bp, false);
        $blueprint = static::blueprintForArea($name, $bp, $model);
        $layout = static::layoutForBlueprint($blueprint);

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
        if (is_array($changesContent) === true) {
            $changesStored = static::valuesFromContent($changesContent, $fieldNames);
            if (empty($changesStored) === false) {
                $hasChanges = static::storedValuesDiffer($latestStored, $changesStored);
                $form->fill(array_replace($latestStored, $changesStored));
                $currentValues = $form->toFormValues();
            }
        }

        $modelPath = static::modelApiPath($model);

        return [
            'id'        => $name,
            'title'     => $blueprint->title(),
            'icon'      => static::resolveIcon($name, $bp, $blueprint),
            'layout'    => $layout,
            'fieldProps' => $fieldProps,
            'fieldSync' => $fieldSync,
            'values'    => $currentValues,
            'baseline'  => $baselineValues,
            'buttons'   => static::viewButtons($blueprint, $model, static::viewId($name)),
            'meta'      => array_merge(static::viewMeta($model), static::changesMeta($model), [
                'menuId' => static::menuId($name),
                'blueprintPath' => static::blueprintDisplayPath($file),
                'isEmpty' => static::blueprintIsEmpty($layout),
                'hasChanges' => $hasChanges,
                'modelPath' => $modelPath,
                'menuBadgeCount' => static::menuBadgeCount(),
            ]),
        ];
    }

    public static function save(string $name, array $values): array
    {
        $file = static::blueprintFile($name);
        if ($file === null) {
            throw new NotFoundException('Blueprint not found');
        }

        $bp = static::readBlueprint($file);
        $bp['name'] = $name;
        $model = static::modelForArea($name, $bp);
        static::requireAreaAccess($model, $bp, true);
        $blueprint = static::blueprintForArea($name, $bp, $model);
        $layout = static::layoutForBlueprint($blueprint);
        $values = static::filterValuesForLayout($layout, $values);
        $form = static::formForBlueprint($layout, [], $model);

        // Apply submitted values (skips disabled fields)
        $form = $form->submit($values);
        $form->validate();

        $stored = $form->toStoredValues();
        $prefixed = static::storedValues($stored);

        $language = static::languageCode();
        $model = $model->update($prefixed, $language);
        static::clearChangesForFields($model, $name, array_keys(static::collectFields($layout)));

        // Return updated view payload
        return static::view($name);
    }

    public static function draft(string $name, array $values): array
    {
        $file = static::blueprintFile($name);
        if ($file === null) {
            throw new NotFoundException('Blueprint not found');
        }

        $bp = static::readBlueprint($file);
        $bp['name'] = $name;
        $model = static::modelForArea($name, $bp);
        static::requireAreaAccess($model, $bp, true);
        $blueprint = static::blueprintForArea($name, $bp, $model);
        $layout = static::layoutForBlueprint($blueprint);
        $values = static::filterValuesForLayout($layout, $values);
        $fields = static::collectFields($layout);
        $fieldNames = array_keys($fields);
        $latestStored = static::valuesFromContent(static::latestContent($model), $fieldNames);

        // Build a baseline form so we can compare against normalized stored values.
        // This avoids "technical" changes like writing empty values for fields
        // that were previously missing from the content.
        $baselineForm = static::formForBlueprint($layout, $latestStored, $model);
        $baselineStored = $baselineForm->toStoredValues();

        $form = static::formForBlueprint($layout, $latestStored, $model);
        $form = $form->submit($values, false);
        $stored = $form->toStoredValues();
        $changed = [];
        $unchanged = [];

        foreach (array_keys($values) as $fieldName) {
            $fieldName = Str::lower((string)$fieldName);
            if (isset($fields[$fieldName]) === false) {
                continue;
            }

            if (array_key_exists($fieldName, $stored) === false) {
                continue;
            }

            $baselineValue = $baselineStored[$fieldName] ?? null;
            $currentValue = $stored[$fieldName];

            // Compare strictly to avoid PHP's loose comparisons (e.g. null == 0).
            if (json_encode($baselineValue) === json_encode($currentValue)) {
                $unchanged[] = $fieldName;
            } else {
                $changed[$fieldName] = $currentValue;
            }
        }

        if (empty($unchanged) === false) {
            static::clearChangesForFields($model, $name, $unchanged);
        }

        if (empty($changed) === false) {
            $prefixed = static::storedValues($changed);
            static::updateChanges($model, $prefixed);
        }

        return [
            'success' => true,
        ];
    }

    public static function discard(string $name): array
    {
        $file = static::blueprintFile($name);
        if ($file === null) {
            throw new NotFoundException('Blueprint not found');
        }

        $bp = static::readBlueprint($file);
        $bp['name'] = $name;
        $model = static::modelForArea($name, $bp);
        static::requireAreaAccess($model, $bp, true);
        $blueprint = static::blueprintForArea($name, $bp, $model);
        $layout = static::layoutForBlueprint($blueprint);
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
        $form = static::formForArea($name, true);
        $field = $form->field(Str::lower($fieldName));

        $fieldApi = $api->clone([
            'data'   => [...$api->data(), 'field' => $field],
            'routes' => $field->api(),
        ]);

        return $fieldApi->call(
            $path,
            $api->requestMethod() ?? 'GET',
            $api->requestData()
        );
    }

    public static function section(string $name, string $sectionName): array
    {
        $file = static::blueprintFile($name);
        if ($file === null) {
            throw new NotFoundException('Blueprint not found');
        }

        $bp = static::readBlueprint($file);
        $bp['name'] = $name;
        $model = static::modelForArea($name, $bp);
        static::requireAreaAccess($model, $bp, false);
        $blueprint = static::blueprintForArea($name, $bp, $model);
        $section = $blueprint->section($sectionName);
        if ($section === null) {
            throw new NotFoundException('Section not found');
        }

        return $section->toResponse();
    }

    public static function sectionApi(
        string $name,
        string $sectionName,
        ?string $path,
        KirbyApi $api
    ): mixed {
        $file = static::blueprintFile($name);
        if ($file === null) {
            throw new NotFoundException('Blueprint not found');
        }

        $bp = static::readBlueprint($file);
        $bp['name'] = $name;
        $model = static::modelForArea($name, $bp);
        static::requireAreaAccess($model, $bp, true);
        $blueprint = static::blueprintForArea($name, $bp, $model);
        $section = $blueprint->section($sectionName);
        if ($section === null) {
            throw new NotFoundException('Section not found');
        }

        $routes = $section->api() ?? [];
        $sectionApi = $api->clone([
            'data'   => [...$api->data(), 'section' => $section],
            'routes' => $routes,
        ]);

        return $sectionApi->call(
            $path,
            $api->requestMethod() ?? 'GET',
            $api->requestData()
        );
    }

    private static function viewMeta(ModelWithContent $model): array
    {
        $modified = null;
        if (method_exists($model, 'modified') === true) {
            $modified = $model->{'modified'}();
        }
        $lastSavedAt = null;
        if (is_int($modified) === true) {
            $lastSavedAt = date(DATE_ATOM, $modified);
        } elseif (is_string($modified) === true && $modified !== '') {
            $lastSavedAt = $modified;
        }

        $lastSavedBy = null;
        if (method_exists($model, 'modifiedBy') === true) {
            $by = $model->{'modifiedBy'}();
            if (is_object($by) === true) {
                $lastSavedBy = static::stringFromUser($by);
            } elseif (is_string($by) === true && $by !== '') {
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

        if (is_string($buttons) === true) {
            $resolved = static::resolveButton($buttons, null, $model, $viewId);
            return $resolved === null ? [] : [$resolved];
        }

        if (is_array($buttons) === true) {
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
        if (class_exists(LanguagesDropdown::class) === false) {
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

        if (is_string($button) === true) {
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

        if (is_array($button) === true) {
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

        if (method_exists($modelWithPreview, 'panel') === false) {
            return null;
        }

        $panel = $model->panel();
        if (is_object($panel) === false || method_exists($panel, 'url') === false) {
            return null;
        }

        $link = $panel->url(true) . '/preview/changes';
        return static::ensureComponent(
            (new PreviewButton($link))->render(),
            'k-preview-view-button'
        );
    }

    private static function settingsButton(ModelWithContent $model): array|null
    {
        if (method_exists($model, 'panel') === false) {
            return null;
        }

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
        if (method_exists($user, 'name') === true) {
            $name = $user->name();
            $nameString = static::stringFromValue($name);
            if ($nameString !== null && $nameString !== '') {
                return $nameString;
            }
        }

        if (method_exists($user, 'email') === true) {
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
        if (is_string($value) === true) {
            return $value;
        }

        if (is_object($value) === true) {
            if (method_exists($value, 'value') === true) {
                $value = $value->value();
                return is_string($value) ? $value : null;
            }

            if (method_exists($value, 'toString') === true) {
                $value = $value->toString();
                return is_string($value) ? $value : null;
            }
        }

        return null;
    }
}
