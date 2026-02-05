<?php

namespace GrommasDietz\Areas\BlueprintAreas;

use Kirby\Cms\App;
use Kirby\Cms\Blueprint;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Data\Data;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;

trait BlueprintsTrait
{
    private static array $titleCache = [];
    private static ?array $reservedAreaIds = null;
    private const MENU_PREFIX = '';

    public static function options(): array
    {
        $kirby = App::instance();
        $defaults = $kirby->plugin('grommasdietz/kirby-blueprint-areas')?->options() ?? [];
        $user = $kirby->option('grommasdietz.kirby-blueprint-areas', []);

        // Kirby merges plugin defaults automatically for $kirby->option(),
        // but we want a single place to normalize.
        return array_replace_recursive($defaults, $user);
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

            if (!static::canAccessArea($model, $bp, false)) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    public static function listAll(): array
    {
        $root = static::blueprintsRoot();
        if ($root === null || !is_dir($root)) {
            return [];
        }

        $files = Dir::files($root);
        $items = [];

        foreach ($files as $file) {
            $path = $root . '/' . $file;
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if (!in_array($ext, ['yml', 'yaml'], true)) {
                continue;
            }

            $name = pathinfo($file, PATHINFO_FILENAME);
            if (static::isReservedAreaId(static::menuId($name))) {
                continue;
            }
            $bp   = static::readBlueprint($path);
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

        return static::canAccessArea($model, $bp, $write);
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

        return App::instance()->root('blueprints') . '/areas';
    }

    private static function blueprintFile(string $name): ?string
    {
        $root = static::blueprintsRoot();
        if ($root === null) {
            return null;
        }

        foreach (['yml', 'yaml'] as $ext) {
            $file = $root . '/' . $name . '.' . $ext;
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    private static function readBlueprint(string $file): array
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
            $icon = $blueprint->icon();
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
        $siteRoot = App::instance()->root('site');
        if (is_string($siteRoot) === true && $siteRoot !== '' && str_starts_with($file, $siteRoot)) {
            $relative = substr($file, strlen($siteRoot));
            if ($relative === '' || $relative[0] !== '/') {
                $relative = '/' . $relative;
            }

            return '/site' . $relative;
        }

        return $file;
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
        return self::MENU_PREFIX . $name;
    }

    private static function isReservedAreaId(string $areaId): bool
    {
        return in_array($areaId, static::reservedAreaIds(), true);
    }

    private static function reservedAreaIds(): array
    {
        if (static::$reservedAreaIds !== null) {
            return static::$reservedAreaIds;
        }

        static::$reservedAreaIds = array_keys(App::instance()->core()->areas());
        return static::$reservedAreaIds;
    }

    private static function viewId(string $name): string
    {
        return 'grommasdietz/kirby-blueprint-areas.' . $name;
    }

    private static function languageCode(): ?string
    {
        $lang = App::instance()->language();
        return $lang?->code();
    }
}
