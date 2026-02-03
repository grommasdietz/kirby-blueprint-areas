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
            if (empty($changesContent) === false) {
                $changesStored = static::valuesFromContent($changesContent, $fieldNames);
                if (empty($changesStored) === false) {
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
                    if (isset($changed[$sync]) === true) {
                        $exclude[$fieldName] = true;
                    }
                }
            }

            $count = 0;
            foreach ($changed as $fieldName => $value) {
                if (isset($exclude[$fieldName]) === false) {
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
        if ($changes === null || method_exists($changes, 'lock') === false) {
            return null;
        }

        $language = static::languageCode() ?? 'default';
        $lock = $changes->lock($language);
        if ($lock === null || method_exists($lock, 'toArray') === false) {
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

        if (method_exists($model, 'version') === true) {
            $latest = $model->version('latest');
            if ($latest !== null && method_exists($latest, 'read') === true) {
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
        if ($changes === null || method_exists($changes, 'exists') === false) {
            return null;
        }

        $language = static::languageCode();
        $hasChanges = $language ? $changes->exists($language) : $changes->exists();
        if ($hasChanges !== true) {
            return null;
        }

        $content = $language ? $changes->read($language) : $changes->read();
        return is_array($content) ? $content : [];
    }

    private static function changesVersion(ModelWithContent $model): mixed
    {
        if (method_exists($model, 'version') === false) {
            return null;
        }

        return $model->version('changes');
    }

    private static function updateChanges(ModelWithContent $model, array $updates): void
    {
        if (empty($updates) === true) {
            return;
        }

        $changes = static::changesVersion($model);
        if ($changes === null || method_exists($changes, 'exists') === false) {
            return;
        }

        $language = static::languageCode();
        $hasChanges = $language ? $changes->exists($language) : $changes->exists();
        $content = $hasChanges ? static::readVersionContent($changes, $language) : [];
        if (is_array($content) === false) {
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
        if ($changes === null || method_exists($changes, 'exists') === false) {
            return;
        }

        $language = static::languageCode();
        $hasChanges = $language ? $changes->exists($language) : $changes->exists();
        if ($hasChanges !== true) {
            return;
        }

        $content = static::readVersionContent($changes, $language);
        if (is_array($content) === false) {
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
            if (isset($reservedKeys[$key]) === false) {
                $hasOtherKeys = true;
                break;
            }
        }

        if ($hasOtherKeys === false) {
            unset($content['lock'], $content['uuid']);
        }

        static::writeChangesContent($changes, $language, $content, true);
    }

    private static function readVersionContent(object|null $version, ?string $language): array
    {
        if ($version === null || method_exists($version, 'read') === false) {
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
            method_exists($changes, 'replace') === false ||
            method_exists($changes, 'create') === false ||
            method_exists($changes, 'delete') === false
        ) {
            return;
        }

        if (empty($content) === true) {
            if ($hasChanges === true) {
                $language ? $changes->delete($language) : $changes->delete();
            }
            return;
        }

        if ($hasChanges === true) {
            $language ? $changes->replace($content, $language) : $changes->replace($content);
            return;
        }

        $language ? $changes->create($content, $language) : $changes->create($content);
    }

    private static function changesMeta(ModelWithContent $model): array
    {
        $changes = static::changesVersion($model);
        if ($changes === null || method_exists($changes, 'exists') === false) {
            return [
                'changesModified' => null,
                'changesBy' => null,
            ];
        }

        $language = static::languageCode();
        $hasChanges = $language ? $changes->exists($language) : $changes->exists();
        if ($hasChanges !== true) {
            return [
                'changesModified' => null,
                'changesBy' => null,
            ];
        }

        $lock = method_exists($changes, 'lock') ? $changes->lock($language ?? 'default') : null;
        $modified = null;
        if ($lock !== null && method_exists($lock, 'modified') === true) {
            $modified = $lock->modified('c', 'date');
        }

        if ($modified === null && method_exists($changes, 'modified') === true) {
            $timestamp = $changes->modified($language ?? 'default');
            if (is_int($timestamp) === true) {
                $modified = date(DATE_ATOM, $timestamp);
            }
        }

        $by = null;
        if ($lock !== null && method_exists($lock, 'user') === true) {
            $user = $lock->user();
            if (is_object($user) === true) {
                $by = static::stringFromUser($user);
            }
        }

        return [
            'changesModified' => $modified,
            'changesBy' => $by,
        ];
    }
}
