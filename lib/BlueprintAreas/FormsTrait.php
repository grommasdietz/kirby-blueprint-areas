<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\ModelWithContent;
use Kirby\Exception\NotFoundException;
use Kirby\Form\Form;
use Kirby\Toolkit\Str;

trait FormsTrait
{
    private static function formForBlueprint(array $layout, array $values, ModelWithContent $model): Form
    {
        $fields = static::collectFields($layout);
        $form = new Form([], $fields, $model, static::languageCode());
        $form->fill($values);

        return $form;
    }

    private static function formForArea(string $name, bool $withChanges = true): Form
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
        $fields = static::collectFields($layout);
        $fieldNames = array_keys($fields);

        $latestStored = static::valuesFromContent(static::latestContent($model), $fieldNames);
        $values = $latestStored;

        if ($withChanges === true) {
            $changesContent = static::changesContent($model);
            if (is_array($changesContent) === true) {
                $changesStored = static::valuesFromContent($changesContent, $fieldNames);
                if (empty($changesStored) === false) {
                    $values = array_replace($latestStored, $changesStored);
                }
            }
        }

        return static::formForBlueprint($layout, $values, $model);
    }

    private static function collectFields(array $layout): array
    {
        $fields = [];
        $tabs = $layout['tabs'] ?? [];
        if (is_array($tabs) === false) {
            return [];
        }

        foreach ($tabs as $tab) {
            foreach (($tab['columns'] ?? []) as $col) {
                foreach (($col['sections'] ?? []) as $section) {
                    if (($section['type'] ?? null) !== 'fields') {
                        continue;
                    }
                    $sectionFields = $section['rawFields'] ?? $section['fields'] ?? [];
                    if (is_array($sectionFields) === false) {
                        $sectionFields = [];
                    }
                    foreach ($sectionFields as $fieldName => $field) {
                        if (is_array($field) === true) {
                            $normalizedName = Str::lower((string)$fieldName);
                            $field['name'] = $normalizedName;
                            $fields[$normalizedName] = $field;
                        }
                    }
                }
            }
        }

        return $fields;
    }

    private static function syncMapFromFields(array $fields): array
    {
        $syncMap = [];

        foreach ($fields as $fieldName => $fieldProps) {
            if (is_array($fieldProps) === false) {
                continue;
            }

            $sync = $fieldProps['sync'] ?? null;
            if (is_string($sync) === true && $sync !== '') {
                $syncMap[$fieldName] = Str::lower($sync);
            }
        }

        return $syncMap;
    }

    private static function filterValuesForLayout(array $layout, array $values): array
    {
        if (empty($values) === true) {
            return [];
        }

        $fields = static::collectFields($layout);
        if ($fields === []) {
            return [];
        }

        $allowed = array_flip(array_keys($fields));
        $filtered = [];

        foreach ($values as $key => $value) {
            $normalized = Str::lower((string)$key);
            if (isset($allowed[$normalized]) === true) {
                $filtered[$normalized] = $value;
            }
        }

        return $filtered;
    }

    private static function valuesFromContent(array $content, array $fieldNames): array
    {
        $values = [];
        foreach ($fieldNames as $fieldName) {
            $key = (string)$fieldName;
            if (array_key_exists($key, $content) === true) {
                $values[$fieldName] = $content[$key];
            }
        }

        return $values;
    }

    private static function storedValuesDiffer(array $latest, array $changes): bool
    {
        foreach ($changes as $key => $value) {
            if (array_key_exists($key, $latest) === false || $latest[$key] != $value) {
                return true;
            }
        }

        return false;
    }

    private static function storedValues(array $stored): array
    {
        $prefixed = [];
        foreach ($stored as $fieldName => $value) {
            $prefixed[(string)$fieldName] = $value;
        }

        return $prefixed;
    }
}
