<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\ModelWithContent;
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

    /**
     * @param array<string, mixed> $context
     */
    private static function formForAreaContext(array $context, bool $withChanges = true): Form
    {
        $model = $context['model'];
        $layout = $context['layout'];
        $fields = static::collectFields($layout);
        $fieldNames = array_keys($fields);

        $latestStored = static::valuesFromContent(static::latestContent($model), $fieldNames);
        $values = $latestStored;

        if ($withChanges) {
            $changesContent = static::changesContent($model);
            if (is_array($changesContent)) {
                $changesStored = static::valuesFromContent($changesContent, $fieldNames);
                if (!empty($changesStored)) {
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
        if (!is_array($tabs)) {
            return [];
        }

        foreach ($tabs as $tab) {
            foreach (($tab['columns'] ?? []) as $col) {
                foreach (($col['sections'] ?? []) as $section) {
                    if (($section['type'] ?? null) !== 'fields') {
                        continue;
                    }
                    $sectionFields = $section['rawFields'] ?? $section['fields'] ?? [];
                    if (!is_array($sectionFields)) {
                        $sectionFields = [];
                    }
                    foreach ($sectionFields as $fieldName => $field) {
                        if (is_array($field)) {
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
            if (!is_array($fieldProps)) {
                continue;
            }

            $sync = $fieldProps['sync'] ?? null;
            if (is_string($sync) && $sync !== '') {
                $syncMap[$fieldName] = Str::lower($sync);
            }
        }

        return $syncMap;
    }

    private static function filterValuesForLayout(array $layout, array $values): array
    {
        if (empty($values)) {
            return [];
        }

        $fields = static::collectFields($layout);
        if ($fields === []) {
            return [];
        }

        $allowed = [];
        foreach ($fields as $fieldName => $fieldProps) {
            if (($fieldProps['disabled'] ?? false) === true) {
                continue;
            }

            $allowed[$fieldName] = true;
        }

        $filtered = [];

        foreach ($values as $key => $value) {
            $normalized = Str::lower((string)$key);
            if (isset($allowed[$normalized])) {
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
            if (array_key_exists($key, $content)) {
                $values[$fieldName] = $content[$key];
            }
        }

        return $values;
    }

    private static function storedValuesDiffer(array $latest, array $changes): bool
    {
        foreach ($changes as $key => $value) {
            if (!array_key_exists($key, $latest) || $latest[$key] != $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $stored
     * @param list<string> $submittedFields
     * @return array<string, mixed>
     */
    private static function submittedStoredValues(
        array $stored,
        array $submittedFields
    ): array {
        $submitted = array_fill_keys($submittedFields, true);

        return array_intersect_key($stored, $submitted);
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
