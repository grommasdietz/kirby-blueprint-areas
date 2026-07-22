<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\App;
use Kirby\Cms\Blueprint;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Permissions;
use Kirby\Cms\Site;
use Kirby\Data\Data;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;

trait BlueprintsTrait
{
    private static array $titleCache = [];
    /** @var list<string> */
    private static array $registeredAreaIds = [];
    /** @var list<string> */
    private static array $registeredPermissionIds = [];
    private const PLUGIN_NAME = 'grommasdietz/blueprint-areas';

    /**
     * @return array{
     *     panel: array{enabled: bool, badgeCount: bool, areaPrefix: string},
     *     'blueprints.root': null,
     *     api: array{legacyPayload: bool, maxPayloadDepth: int, maxPayloadBytes: null}
     * }
     */
    public static function defaultOptions(): array
    {
        return [
            'panel' => [
                'enabled' => true,
                'badgeCount' => false,
                'areaPrefix' => '',
            ],
            'blueprints.root' => null,
            'api' => [
                'legacyPayload' => true,
                'maxPayloadDepth' => 32,
                'maxPayloadBytes' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        $kirby = App::instance();
        $defaults = static::defaultOptions();
        $extensions = $kirby->plugin(self::PLUGIN_NAME)?->extends() ?? [];
        $registered = is_array($extensions['options'] ?? null)
            ? $extensions['options']
            : [];
        $user = $kirby->option('grommasdietz.blueprint-areas', []);

        return array_replace_recursive(
            $defaults,
            $registered,
            is_array($user) === true ? $user : [],
        );
    }

    public static function list(): array
    {
        if (static::currentUser() === null) {
            return [];
        }

        $items = [];

        foreach (static::listAll() as $item) {
            $name = $item['id'];
            $file = static::blueprintFile($name);
            if ($file === null) {
                continue;
            }

            $bp = static::readBlueprint($file);
            $bp['name'] = $name;
            try {
                $model = static::modelForArea($name, $bp);
            } catch (\Throwable) {
                continue;
            }

            if (!static::canAccessArea($model, $bp, self::AREA_OPERATION_READ)) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    public static function listAll(): array
    {
        $items = [];

        foreach (static::blueprintFiles() as $name => $path) {
            if (static::isReservedAreaId(static::menuId($name))) {
                continue;
            }

            $bp = static::readBlueprint($path);
            $bp['name'] = $name;
            try {
                $model = static::modelForArea($name, $bp);
            } catch (\Throwable) {
                continue;
            }

            $blueprint = static::blueprintForArea($name, $bp, $model);

            $items[] = [
                'id'    => $name,
                'title' => $blueprint->title(),
                'icon'  => static::resolveIcon($name, $bp, $blueprint),
                'info'  => $bp['info'] ?? null,
            ];
        }

        usort($items, static fn ($a, $b) => strcmp($a['title'], $b['title']));

        return $items;
    }

    public static function listForRegistration(): array
    {
        $items = [];

        foreach (static::blueprintFiles() as $name => $path) {
            if (static::isReservedAreaId(static::menuId($name))) {
                continue;
            }

            $bp = static::readBlueprint($path);
            $items[] = [
                'id'    => $name,
                'title' => static::titleFromRawBlueprint($name, $bp),
                'icon'  => static::resolveIcon($name, $bp),
            ];
        }

        usort($items, static fn ($a, $b) => strcmp($a['title'], $b['title']));

        return $items;
    }

    private static function titleFromRawBlueprint(string $name, array $bp): string
    {
        $title = $bp['title'] ?? null;

        if (is_string($title) && $title !== '') {
            return $title;
        }

        if (is_array($title)) {
            $language = static::languageCode();
            if (is_string($language) && isset($title[$language]) && is_string($title[$language])) {
                return $title[$language];
            }

            $first = reset($title);
            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        return Str::ucfirst(str_replace(['-', '_'], ' ', $name));
    }

    public static function canAccess(string $name, bool $write = false): bool
    {
        if (static::currentUser() === null) {
            return false;
        }

        $file = static::blueprintFile($name);
        if ($file === null) {
            return false;
        }

        $bp = static::readBlueprint($file);
        $bp['name'] = $name;

        try {
            $model = static::modelForArea($name, $bp);
        } catch (\Throwable) {
            return false;
        }

        return static::canAccessArea(
            $model,
            $bp,
            $write ? self::AREA_OPERATION_UPDATE : self::AREA_OPERATION_READ
        );
    }

    public static function title(string $name): string
    {
        if (isset(static::$titleCache[$name])) {
            return static::$titleCache[$name];
        }

        $file = static::blueprintFile($name);
        if ($file === null) {
            $title = Str::ucfirst(str_replace(['-', '_'], ' ', $name));
            return static::$titleCache[$name] = $title;
        }

        $bp = static::readBlueprint($file);
        $title = static::titleFromBlueprint($name, $bp);
        return static::$titleCache[$name] = $title;
    }

    private static function titleFromBlueprint(string $name, array $bp): string
    {
        $model = static::modelForArea($name, $bp);
        $blueprint = static::blueprintForArea($name, $bp, $model);
        return $blueprint->title();
    }

    private static function blueprintsRoot(): string
    {
        $opts = static::options();
        $root = $opts['blueprints.root'] ?? null;
        if (is_string($root) && $root !== '') {
            return $root;
        }

        $blueprintsRoot = App::instance()->root('blueprints');
        if (!is_string($blueprintsRoot) || $blueprintsRoot === '') {
            return '';
        }

        return $blueprintsRoot . '/areas';
    }

    /**
     * @return array<string, string>
     */
    private static function blueprintFiles(): array
    {
        $root = static::blueprintsRoot();
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        foreach (Dir::files($root) as $filename) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($extension, ['yml', 'yaml'], true)) {
                continue;
            }

            $name = pathinfo($filename, PATHINFO_FILENAME);
            if (!static::isValidAreaName($name)) {
                continue;
            }

            // Preserve the historical .yml precedence if both extensions exist.
            if (!isset($files[$name]) || $extension === 'yml') {
                $files[$name] = $root . '/' . $filename;
            }
        }

        ksort($files);

        return $files;
    }

    private static function blueprintFile(string $name): ?string
    {
        if (!static::isValidAreaName($name)) {
            return null;
        }

        return static::blueprintFiles()[$name] ?? null;
    }

    private static function isValidAreaName(string $name): bool
    {
        return $name !== ''
            && $name !== '.'
            && $name !== '..'
            && !str_contains($name, '/')
            && !str_contains($name, '\\')
            && !str_contains($name, "\0");
    }

    /**
     * @return array<array-key, mixed>
     */
    protected static function readBlueprint(string $file): array
    {
        $data = Data::read($file);
        return is_array($data) ? $data : [];
    }

    private static function blueprintForArea(string $name, array $props, ModelWithContent $model): Blueprint
    {
        $props = is_array($props) ? $props : [];
        $props['name'] ??= $name;
        $props['model'] = $model;

        return new Blueprint($props);
    }

    private static function modelForArea(string $name, array $bp): ModelWithContent
    {
        $model = static::site();

        $query = $bp['query'] ?? null;
        if (is_string($query) && $query !== '') {
            $resolved = $model->query($query, ModelWithContent::class);
            if ($resolved instanceof ModelWithContent) {
                return $resolved;
            }

            throw new NotFoundException('Model query did not resolve');
        }

        return $model;
    }

    private static function layoutForBlueprint(Blueprint $blueprint): array
    {
        return [
            'tabs' => $blueprint->tabs(),
        ];
    }

    private static function blueprintIsEmpty(array $layout): bool
    {
        $tabs = $layout['tabs'] ?? [];
        if (is_array($tabs) === false || $tabs === []) {
            return true;
        }

        foreach ($tabs as $tab) {
            if (is_array($tab) === true && static::tabHasContent($tab) === true) {
                return false;
            }
        }

        return true;
    }

    private static function tabHasContent(array $tab): bool
    {
        if (isset($tab['fields']) === true && is_array($tab['fields']) === true && $tab['fields'] !== []) {
            return true;
        }

        if (isset($tab['sections']) === true && is_array($tab['sections']) === true && $tab['sections'] !== []) {
            return true;
        }

        if (isset($tab['columns']) === true && is_array($tab['columns']) === true) {
            foreach ($tab['columns'] as $column) {
                if (is_array($column) === false) {
                    continue;
                }

                if (isset($column['fields']) === true && is_array($column['fields']) === true && $column['fields'] !== []) {
                    return true;
                }

                if (isset($column['sections']) === true && is_array($column['sections']) === true && $column['sections'] !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function resolveIcon(string $name, array $bp, ?Blueprint $blueprint = null): string
    {
        if ($blueprint !== null) {
            $icon = $blueprint->__call('icon');
            if (is_string($icon) === true && $icon !== '') {
                return $icon;
            }
        }

        if (isset($bp['icon']) === true && is_string($bp['icon']) === true && $bp['icon'] !== '') {
            return $bp['icon'];
        }

        // Safe default
        return 'cog';
    }

    private static function menuBadgeCount(): bool
    {
        $opts = static::options();
        $panel = $opts['panel'] ?? [];
        return ($panel['badgeCount'] ?? false) === true;
    }

    private static function site(): Site
    {
        return App::instance()->site();
    }

    private static function blueprintDisplayPath(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $siteRoot = str_replace('\\', '/', (string)App::instance()->root('site'));

        if (static::pathIsWithin($file, $siteRoot)) {
            $relative = ltrim(substr($file, strlen(rtrim($siteRoot, '/'))), '/');
            return '/site/' . $relative;
        }

        $blueprintsRoot = str_replace('\\', '/', static::blueprintsRoot());
        if (static::pathIsWithin($file, $blueprintsRoot)) {
            $relative = ltrim(substr($file, strlen(rtrim($blueprintsRoot, '/'))), '/');
            return '/areas/' . $relative;
        }

        return '/areas/' . basename($file);
    }

    private static function pathIsWithin(string $file, string $root): bool
    {
        $root = rtrim($root, '/');
        if ($root === '') {
            return false;
        }

        return $file === $root || str_starts_with($file, $root . '/');
    }

    private static function modelApiPath(ModelWithContent $model): ?string
    {
        if ($model instanceof Site) {
            return 'site';
        }

        if ($model instanceof Page) {
            return 'pages/' . $model->id();
        }

        return null;
    }

    public static function menuId(string $name): string
    {
        $options = static::options();
        $panel = is_array($options['panel'] ?? null) ? $options['panel'] : [];
        $prefix = $panel['areaPrefix'] ?? '';
        if (!is_string($prefix) || preg_match('/^[A-Za-z0-9._-]*$/', $prefix) !== 1) {
            $prefix = '';
        }

        return $prefix . $name;
    }

    /**
     * Clears area IDs from an earlier App instance before plugin registration.
     */
    public static function beginAreaRegistration(): void
    {
        foreach (static::$registeredAreaIds as $areaId) {
            if (property_exists(Permissions::class, 'extendedAreas')) {
                unset(Permissions::$extendedAreas[$areaId]);
            }
        }

        foreach (static::$registeredPermissionIds as $permissionId) {
            unset(Permissions::$extendedActions['areas'][$permissionId]);
        }

        static::$registeredAreaIds = [];
        static::$registeredPermissionIds = [];
        static::$titleCache = [];
    }

    /**
     * @param list<string> $areaIds
     * @param list<string> $permissionIds
     */
    public static function completeAreaRegistration(
        array $areaIds,
        array $permissionIds = []
    ): void {
        static::$registeredAreaIds = static::validRegistrationIds($areaIds);
        static::$registeredPermissionIds = static::validRegistrationIds($permissionIds);
    }

    /**
     * @param array<array-key, mixed> $ids
     * @return list<string>
     */
    private static function validRegistrationIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            $ids,
            static fn (mixed $id): bool => is_string($id) && $id !== ''
        )));
    }

    private static function isReservedAreaId(string $areaId): bool
    {
        return in_array($areaId, static::reservedAreaIds(), true);
    }

    private static function reservedAreaIds(): array
    {
        $kirby = App::instance();
        $own = array_flip(static::$registeredAreaIds);
        $reserved = array_keys($kirby->core()->areas());

        foreach ($kirby->extensions('areas') as $areaId => $definitions) {
            $isOwn = isset($own[$areaId]);
            $hasCompetingDefinition = is_array($definitions) && count($definitions) > 1;

            if ($isOwn === false || $hasCompetingDefinition === true) {
                $reserved[] = (string)$areaId;
            }
        }

        if (property_exists(Permissions::class, 'extendedAreas')) {
            foreach (array_keys(Permissions::$extendedAreas) as $areaId) {
                if (isset($own[$areaId]) === false) {
                    $reserved[] = (string)$areaId;
                }
            }
        }

        return array_values(array_unique($reserved));
    }

    private static function viewId(string $name): string
    {
        return self::PLUGIN_NAME . '.' . $name;
    }

    private static function languageCode(): ?string
    {
        $lang = App::instance()->language();
        return $lang?->code();
    }
}
