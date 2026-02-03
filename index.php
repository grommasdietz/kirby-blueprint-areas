<?php

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Permissions;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use Kirby\Filesystem\F;
use GrommasDietz\Areas\BlueprintAreas;

// Ensure plugin classes are available when installed as a zip/submodule (no Composer autoload)
F::loadClasses([
    'GrommasDietz\\Areas\\BlueprintAreas' => 'lib/BlueprintAreas.php',
], __DIR__);

if (class_exists(Permissions::class)) {
    Permissions::$extendedActions['areas'] ??= [];
}

Kirby::plugin('grommasdietz/kirby-blueprint-areas', [
    'options' => [
        'panel' => [
            // Set to false to hide all auto-registered menu entries.
            'enabled' => true,
            // Show a numeric badge instead of a dot on menu items
            'badgeCount' => false,
        ],

        // Blueprints storage
        // Default: site/blueprints/areas
        'blueprints.root' => null,
    ],

    // Dedicated API routes for saving
    'api' => [
        'routes' => [
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints',
                'action'  => function () {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::list();
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)',
                'action'  => function (string $name) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::view($name);
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)',
                'method'  => 'POST',
                'action'  => function (string $name) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::save($name, BlueprintAreas::requestValues());
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)/save',
                'method'  => 'POST',
                'action'  => function (string $name) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::draft($name, BlueprintAreas::requestValues());
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)/publish',
                'method'  => 'POST',
                'action'  => function (string $name) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::save($name, BlueprintAreas::requestValues());
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)/discard',
                'method'  => 'POST',
                'action'  => function (string $name) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::discard($name);
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)/changes/save',
                'method'  => 'POST',
                'action'  => function (string $name) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::draft($name, BlueprintAreas::requestValues());
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)/changes/discard',
                'method'  => 'POST',
                'action'  => function (string $name) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::discard($name);
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)/changes/publish',
                'method'  => 'POST',
                'action'  => function (string $name) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::save($name, BlueprintAreas::requestValues());
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/changes',
                'action'  => function () {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::changesSummary();
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)/sections/(:any)',
                'method'  => 'GET',
                'action'  => function (string $name, string $section) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::section($name, $section);
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)/sections/(:any)/(:all?)',
                'method'  => 'ALL',
                'action'  => function (string $name, string $section, string|null $path = null) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::sectionApi($name, $section, $path, $this);
                }
            ],
            [
                'pattern' => 'grommasdietz/kirby-blueprint-areas/blueprints/(:any)/fields/(:any)/(:all?)',
                'method'  => 'ALL',
                'action'  => function (string $name, string $field, string|null $path = null) {
                    if (kirby()->user() === null) {
                        throw new PermissionException('Not authenticated');
                    }

                    return BlueprintAreas::fieldApi($name, $field, $path, $this);
                }
            ],
        ],
    ],

    // One panel area per area blueprint file
    // Generated dynamically from site/blueprints/areas/*.yml
    'areas' => (function () {
        $opts = BlueprintAreas::options();
        $panel = $opts['panel'] ?? [];

        $menuEnabled = ($panel['enabled'] ?? true) === true;

        $areas = [];
        foreach (BlueprintAreas::listAll() as $item) {
            $slug = $item['id'];
            $icon = $item['icon'] ?? 'cog';
            $areaId = BlueprintAreas::menuId($slug);

            $areas[$areaId] = function () use ($areaId, $slug, $icon, $menuEnabled) {
                return [
                    'label' => BlueprintAreas::title($slug),
                    'icon'  => $icon,
                    'menu'  => function () use ($menuEnabled, $slug) {
                        if ($menuEnabled !== true) {
                            return false;
                        }

                        return BlueprintAreas::canAccess($slug);
                    },
                    'link'  => $areaId,
                    'views' => [
                        [
                            'pattern' => $areaId,
                            'action'  => function () use ($slug, $areaId) {
                                $view = BlueprintAreas::view($slug);
                                if (empty($view)) {
                                    throw new NotFoundException('Blueprint not found');
                                }

                                return [
                                    'title' => BlueprintAreas::title($slug),
                                    'component' => 'k-areas-view',
                                    'props' => [
                                        'area' => $view,
                                        'api' => 'grommasdietz/kirby-blueprint-areas/blueprints/' . $slug,
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

        return $areas;
    })(),
]);
