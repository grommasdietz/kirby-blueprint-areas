# Plugin blueprints

Plugins and themes can supply area blueprints alongside a project's own areas.
Register each YAML file directly in the plugin's `blueprints` extension under
an `areas/` key:

```php
Kirby::plugin('my/theme', [
  'blueprints' => [
    'areas/design' => __DIR__ . '/blueprints/areas/design.yml',
  ],
]);
```

The file uses the same [area blueprint format](./index.md#area-blueprint) as a
project blueprint. Its logical ID is `design`, and its Panel URL is
`/panel/design` unless an area prefix is configured. A project does not need a
`site/blueprints/areas` directory to use areas supplied by a plugin.

## Supported registrations

Discovery accepts existing `.yml` and `.yaml` files with one area name after
`areas/`. Nested keys such as `areas/theme/design`, missing files, PHP files,
arrays and callbacks are ignored. Kirby supports some of these forms for other
blueprints; this plugin's area discovery requires YAML file paths.

Declare the paths in the plugin registration. Panel areas are registered during
`system.loadPlugins:after`, after Kirby has applied the plugins' declared
extensions. Do not defer the blueprint registration to a later hook or a
`ready` callback.

## Project overrides

A file with the same area name in `site/blueprints/areas` replaces the
plugin-supplied blueprint. For example, `site/blueprints/areas/design.yml`
overrides `areas/design`. The two blueprints are not merged. If both local
extensions exist, `.yml` takes precedence over `.yaml`.

When `blueprints.root` is configured, that directory supplies the project
overrides instead. The default site directory is not scanned as well.
Plugin-supplied areas remain available, including when the custom directory
does not exist.

## IDs, permissions and storage

Choose distinctive area IDs and field keys. A plugin's name does not namespace
either one. Registering the same blueprint key in several plugins follows
Kirby's extension order; avoid relying on that order to select a blueprint.

An ID already owned by a core or another plugin's Panel area is skipped. Use
[`panel.areaPrefix`](./architecture.md#routes-and-area-ids) when Panel area IDs
need a prefix. The prefix changes the Panel URL and native access permission
key; the logical API ID and legacy `permissions.areas` key stay unchanged.

Plugin-supplied areas follow the same [access rules](./index.md#access-control)
as project areas. Their origin does not grant extra permissions or bypass a
model's update restriction.

Values are stored on the site unless the blueprint supplies a
[`query`](./index.md#query). Areas that use the same model and field keys share
values, drafts and locks. See [Storage](./architecture.md#storage) before
choosing keys for a reusable theme or plugin.

Next: Continue with [Architecture](./architecture.md)
