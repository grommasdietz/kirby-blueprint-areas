<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\ModelWithContent;

trait ChangesTrait
{
    public static function changesSummary(): array
    {
        $areas = [];

        foreach (static::list() as $item) {
            $name = $item['id'];
            $file = static::blueprintFile($name);
            if ($file === null) {
                continue;
            }

            $bp = static::readBlueprint($file);
            $model = static::modelForArea($name, $bp);
            $blueprint = static::blueprintForArea($name, $bp, $model);
            $layout = static::layoutForBlueprint($blueprint);
            $fields = static::collectFields($layout);
            $fieldNames = array_keys($fields);

            $latestContent = static::latestContent($model);
            $changesContent = static::changesContent($model) ?? [];
            $latestStored = static::valuesFromContent($latestContent, $fieldNames);
            $form = static::formForBlueprint($layout, $latestStored, $model);
            $baselineValues = $form->toFormValues();

            $currentValues = $baselineValues;
            if (!empty($changesContent)) {
                $changesStored = static::valuesFromContent($changesContent, $fieldNames);
                if (!empty($changesStored)) {
                    $form->fill(array_replace($latestStored, $changesStored));
                    $currentValues = $form->toFormValues();
                }
            }

            $changed = [];
            foreach ($fieldNames as $fieldName) {
                $baseline = $baselineValues[$fieldName] ?? null;
                $current = $currentValues[$fieldName] ?? null;
                if ($baseline != $current) {
                    $changed[$fieldName] = true;
                }
            }

            $exclude = [];
            if ($changed !== []) {
                $syncMap = static::syncMapFromFields($fields);
                foreach ($syncMap as $fieldName => $sync) {
                    if (isset($changed[$sync])) {
                        $exclude[$fieldName] = true;
                    }
                }
            }

            $count = 0;
            foreach ($changed as $fieldName => $value) {
                if (!isset($exclude[$fieldName])) {
                    $count++;
                }
            }

            $areas[] = [
                'id' => static::menuId($name),
                'count' => $count,
            ];
        }

        return [
            'areas' => $areas,
            'menuBadgeCount' => static::menuBadgeCount(),
        ];
    }

    public static function changesLock(ModelWithContent $model): array|null
    {
        $changes = static::changesVersion($model);
        if ($changes === null || !method_exists($changes, 'lock')) {
            return null;
        }

        $language = static::languageCode() ?? 'default';
        $lock = $changes->lock($language);
        if ($lock === null || !method_exists($lock, 'toArray')) {
            return null;
        }

        return $lock->toArray();
    }

    public static function changesLockForArea(string $name): array|null
    {
        $file = static::blueprintFile($name);
        if ($file === null) {
            return null;
        }

        $bp = static::readBlueprint($file);
        $bp['name'] = $name;
        $model = static::modelForArea($name, $bp);
        static::requireAreaAccess($model, $bp, false);
        return static::changesLock($model);
    }

    private static function latestContent(ModelWithContent $model): array
    {
        $language = static::languageCode();

        if (method_exists($model, 'version')) {
            $latest = $model->version('latest');
            if ($latest !== null && method_exists($latest, 'read')) {
                $content = $language ? $latest->read($language) : $latest->read();
                return is_array($content) ? $content : [];
            }
        }

        $content = $language ? $model->content($language)->toArray() : $model->content()->toArray();
        return is_array($content) ? $content : [];
    }

    private static function changesContent(ModelWithContent $model): ?array
    {
        $changes = static::changesVersion($model);
        if ($changes === null || !method_exists($changes, 'exists')) {
            return null;
        }

        $language = static::languageCode();
        $hasChanges = $language ? $changes->exists($language) : $changes->exists();
        if (!$hasChanges) {
            return null;
        }

        $content = $language ? $changes->read($language) : $changes->read();
        return is_array($content) ? $content : [];
    }

    private static function changesVersion(ModelWithContent $model): mixed
    {
        if (!method_exists($model, 'version')) {
            return null;
        }

        return $model->version('changes');
    }

    private static function updateChanges(ModelWithContent $model, array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        $changes = static::changesVersion($model);
        if ($changes === null || !method_exists($changes, 'exists')) {
            return;
        }

        $language = static::languageCode();
        $hasChanges = $language ? $changes->exists($language) : $changes->exists();
        $content = $hasChanges ? static::readVersionContent($changes, $language) : [];
        if (!is_array($content)) {
            $content = [];
        }

        foreach ($updates as $key => $value) {
            $content[$key] = $value;
        }

        static::writeChangesContent($changes, $language, $content, $hasChanges);
    }

    private static function clearChangesForFields(
        ModelWithContent $model,
        string $name,
        array $fieldNames
    ): void {
        $changes = static::changesVersion($model);
        if ($changes === null || !method_exists($changes, 'exists')) {
            return;
        }

        $language = static::languageCode();
        $hasChanges = $language ? $changes->exists($language) : $changes->exists();
        if (!$hasChanges) {
            return;
        }

        $content = static::readVersionContent($changes, $language);
        if (!is_array($content)) {
            return;
        }

        foreach ($fieldNames as $fieldName) {
            $key = (string)$fieldName;
            unset($content[$key]);
        }

        // Kirby versions may include additional metadata keys (e.g. `uuid`)
        // that shouldn't keep the changes version (and its lock) alive.
        $reservedKeys = [
            'lock' => true,
            'uuid' => true,
        ];

        $hasOtherKeys = false;
        foreach (array_keys($content) as $key) {
            if (!isset($reservedKeys[$key])) {
                $hasOtherKeys = true;
                break;
            }
        }

        if (!$hasOtherKeys) {
            unset($content['lock'], $content['uuid']);
        }

        static::writeChangesContent($changes, $language, $content, true);
    }

    private static function readVersionContent(object|null $version, ?string $language): array
    {
        if ($version === null || !method_exists($version, 'read')) {
            return [];
        }

        $content = $language ? $version->read($language) : $version->read();
        return is_array($content) ? $content : [];
    }

    private static function writeChangesContent(
        object|null $changes,
        ?string $language,
        array $content,
        bool $hasChanges
    ): void {
        if (
            $changes === null ||
            !method_exists($changes, 'replace') ||
            !method_exists($changes, 'create') ||
            !method_exists($changes, 'delete')
        ) {
            return;
        }

        if (empty($content)) {
            if ($hasChanges) {
                $language ? $changes->delete($language) : $changes->delete();
            }
            return;
        }

        if ($hasChanges) {
            $language ? $changes->replace($content, $language) : $changes->replace($content);
            return;
        }

        $language ? $changes->create($content, $language) : $changes->create($content);
    }

    private static function changesMeta(ModelWithContent $model): array
    {
        $changes = static::changesVersion($model);
        if ($changes === null || !method_exists($changes, 'exists')) {
            return [
                'changesModified' => null,
                'changesBy' => null,
            ];
        }

        $language = static::languageCode();
        $hasChanges = $language ? $changes->exists($language) : $changes->exists();
        if (!$hasChanges) {
            return [
                'changesModified' => null,
                'changesBy' => null,
            ];
        }

        $lock = method_exists($changes, 'lock') ? $changes->lock($language ?? 'default') : null;
        $modified = null;
        if ($lock !== null && method_exists($lock, 'modified')) {
            $modified = $lock->modified('c', 'date');
        }

        if ($modified === null && method_exists($changes, 'modified')) {
            $timestamp = $changes->modified($language ?? 'default');
            if (is_int($timestamp)) {
                $modified = date(DATE_ATOM, $timestamp);
            }
        }

        $by = null;
        if ($lock !== null && method_exists($lock, 'user')) {
            $user = $lock->user();
            if (is_object($user)) {
                $by = static::stringFromUser($user);
            }
        }

        return [
            'changesModified' => $modified,
            'changesBy' => $by,
        ];
    }
}
