<?php

use Kirby\Cms\Api as KirbyApi;
use Kirby\Cms\App;
use Kirby\Cms\Permissions;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\F;
use GrommasDietz\Areas\BlueprintAreas;

// Ensure plugin classes are available when installed as a zip/submodule (no Composer autoload)
F::loadClasses([
    'GrommasDietz\\Areas\\BlueprintAreas' => 'lib/BlueprintAreas.php',
], __DIR__);

$methodNotAllowed = function (): never {
    throw new KirbyException(
        message: 'Method not allowed',
        httpCode: 405
    );
};

$saveArea = function (string $name): array {
    /**
     * Kirby binds API route actions to the API instance.
     *
     * @var KirbyApi $this
     * @psalm-suppress InvalidScope
     */
    return BlueprintAreas::save(
        $name,
        BlueprintAreas::requestValues($this->requestBody())
    );
};

$draftArea = function (string $name): array {
    /**
     * Kirby binds API route actions to the API instance.
     *
     * @var KirbyApi $this
     * @psalm-suppress InvalidScope
     */
    return BlueprintAreas::draft(
        $name,
        BlueprintAreas::requestValues($this->requestBody())
    );
};

App::plugin('grommasdietz/blueprint-areas', [
    'options' => BlueprintAreas::defaultOptions(),

    // Authenticated API routes with operation-aware model authorization
    'api' => [
        'routes' => [
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints',
                'auth'    => true,
                'action'  => fn () => BlueprintAreas::list(),
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)',
                'auth'    => true,
                'action'  => fn (string $name) => BlueprintAreas::view($name),
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)',
                'method'  => 'POST',
                'auth'    => true,
                'action'  => $saveArea,
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)/save',
                'method'  => 'POST',
                'auth'    => true,
                'action'  => $draftArea,
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)/publish',
                'method'  => 'POST',
                'auth'    => true,
                'action'  => $saveArea,
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)/discard',
                'method'  => 'POST',
                'auth'    => true,
                'action'  => fn (string $name) => BlueprintAreas::discard($name),
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/changes',
                'auth'    => true,
                'action'  => fn () => BlueprintAreas::changesSummary(),
            ],
            // Explicit fallbacks distinguish known resources from unknown routes.
            // Kirby's router otherwise reports a method mismatch as a generic 404.
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints',
                'method'  => 'ALL',
                'auth'    => true,
                'action'  => $methodNotAllowed,
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)',
                'method'  => 'ALL',
                'auth'    => true,
                'action'  => $methodNotAllowed,
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)/save',
                'method'  => 'ALL',
                'auth'    => true,
                'action'  => $methodNotAllowed,
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)/publish',
                'method'  => 'ALL',
                'auth'    => true,
                'action'  => $methodNotAllowed,
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)/discard',
                'method'  => 'ALL',
                'auth'    => true,
                'action'  => $methodNotAllowed,
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/changes',
                'method'  => 'ALL',
                'auth'    => true,
                'action'  => $methodNotAllowed,
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)/sections/(:any)',
                'method'  => 'GET',
                'auth'    => true,
                'action'  => fn (string $name, string $section) => BlueprintAreas::section($name, $section),
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)/sections/(:any)/(:all?)',
                'method'  => 'ALL',
                'auth'    => true,
                'action'  => function (string $name, string $section, string|null $path = null): mixed {
                    /**
                     * Kirby binds API route actions to the API instance.
                     *
                     * @psalm-suppress InvalidScope
                     */
                    return BlueprintAreas::sectionApi($name, $section, $path, $this);
                },
            ],
            [
                'pattern' => 'grommasdietz/blueprint-areas/blueprints/(:any)/fields/(:any)/(:all?)',
                'method'  => 'ALL',
                'auth'    => true,
                'action'  => function (string $name, string $field, string|null $path = null): mixed {
                    /**
                     * Kirby binds API route actions to the API instance.
                     *
                     * @psalm-suppress InvalidScope
                     */
                    return BlueprintAreas::fieldApi($name, $field, $path, $this);
                },
            ],
        ],
    ],

    // One panel area per area blueprint file
    // Generated dynamically from site/blueprints/areas/*.yml
    'areas' => (function () {
        $opts = BlueprintAreas::options();
        $panel = $opts['panel'] ?? [];

        $menuEnabled = ($panel['enabled'] ?? true) === true;
        BlueprintAreas::beginAreaRegistration();
        $registered = BlueprintAreas::listForRegistration();

        $legacyPermissions = [];
        if (class_exists(Permissions::class)) {
            foreach ($registered as $item) {
                $legacyPermissions[BlueprintAreas::menuId($item['id'])] = true;
                $legacyPermissions[$item['id']] = true;
            }

            $existingPermissions = Permissions::$extendedActions['areas'] ?? [];
            if (!is_array($existingPermissions)) {
                $existingPermissions = [];
            }

            Permissions::$extendedActions['areas'] = [
                ...$existingPermissions,
                ...$legacyPermissions,
            ];
        }

        $areas = [];
        foreach ($registered as $item) {
            $slug = $item['id'];
            $icon = $item['icon'] ?? 'cog';
            $areaId = BlueprintAreas::menuId($slug);

            $areas[$areaId] = function () use ($areaId, $slug, $icon, $menuEnabled): array {
                return [
                    'label' => BlueprintAreas::title($slug),
                    'icon'  => $icon,
                    'menu'  => function () use ($menuEnabled, $slug): bool {
                        if ($menuEnabled !== true) {
                            return false;
                        }

                        return BlueprintAreas::canAccess($slug);
                    },
                    'link'  => $areaId,
                    'views' => [
                        [
                            'pattern' => $areaId,
                            'action'  => function () use ($slug, $areaId): array {
                                $view = BlueprintAreas::view($slug);
                                if (empty($view)) {
                                    throw new NotFoundException('Blueprint not found');
                                }

                                return [
                                    'title' => BlueprintAreas::title($slug),
                                    'component' => 'k-areas-view',
                                    'props' => [
                                        'area' => $view,
                                        'api' => 'grommasdietz/blueprint-areas/blueprints/' . $slug,
                                        'lock' => BlueprintAreas::changesLockForArea($slug),
                                        'versions' => [
                                            'latest' => $view['baseline'] ?? [],
                                            'changes' => $view['values'] ?? [],
                                        ],
                                    ],
                                ];
                            }
                        ],
                    ],
                ];
            };
        }

        BlueprintAreas::completeAreaRegistration(array_keys($areas), array_keys($legacyPermissions));

        return $areas;
    })(),
]);
