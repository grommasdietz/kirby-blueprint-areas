# Usage

Kirby Blueprint Areas registers each area blueprint as a Panel area and stores values on the resolved model (site by default).

## Area blueprint

Define area blueprints like Kirby’s default site, page or file blueprints as YAML files in `site/blueprints/areas/*.yml`.

### Example

```yml
# site/blueprints/areas/settings.yml
title: Settings
icon: cog

tabs:
  general:
    label: General
    columns:
      - width: 1/1
        sections:
          settings:
            type: fields
            fields:
              settings_headline:
                label: Headline
                type: text
              settings_maintenance:
                label: Maintenance mode
                type: toggle
              settings_cdn:
                label: CDN base URL
                type: url
```

> [!NOTE]
> All native Kirby fields and core sections work in area blueprints. Custom sections work when they register a Panel component and expose API routes.

## Translating the area label

The Panel label (menu entry) and the view title are taken from the area blueprint’s `title`.

To translate it, either use a translation key:

```yml
# site/blueprints/areas/settings.yml
title: areas.settings
```

…and define the translations in your language files:

```php
// site/languages/en.php
return [
  // ...
  'translations' => [
    'areas.settings' => 'Settings',
  ],
];
```

Or provide per-locale values directly in the blueprint:

```yml
title:
  en: Settings
  de: Einstellungen
```

## Content languages

Area fields read and write the current content language of the resolved model.
The playground `translations` area demonstrates this with one text field that
contains different seeded values in English and German. Use the language switch
in the Panel header to compare them.

## Query

Add a `query` to bind an area to another model:

```yml
# site/blueprints/areas/home.yml
title: Home
icon: home
query: site.find("home")
```

The area then reads and writes values on the resolved model instead of the site. The query uses Kirby’s query language and must resolve to a `ModelWithContent`, or the Panel view returns an error.

## Access control

Restrict a single area via its blueprint:

```yml
title: Settings
icon: cog

options:
  access:
    *: false
    admin: true
```

> [!NOTE]
> `access` targets role ids (e.g. `admin`, `editor`) and can only further restrict access.

Or restrict a registered Panel area via Kirby's native `access` permissions in user role blueprints:

```yml
permissions:
  access:
    home: false
```

> [!NOTE]
> Each area blueprint is registered as a Panel area, so Kirby automatically accepts `permissions.access.<area-id>` for it. No additional permission registration is required.

Existing sites can still use the legacy `areas` permission bucket:

```yml
permissions:
  areas:
    "*": false
    home: true
```

> [!NOTE]
> The `areas` bucket is registered by this plugin for discovered area blueprints for backward compatibility. Prefer `access` for new projects because it is Kirby's native Panel area permission.

> [!IMPORTANT]
> Area access never overrides the resolved model permissions. Site-backed areas require `site.access`; page-backed areas require both `pages.access` and `pages.read`. Saving, publishing, discarding and mutating field/section API routes additionally require the model's `update` permission. A role can therefore be read-only by allowing access/read while denying update; the Panel then hides save controls and disables field inputs.

## API payloads

Save and draft endpoints use an explicit values envelope:

```json
{
  "values": {
    "settings_headline": "Example"
  }
}
```

Legacy direct field maps remain enabled by default so existing integrations keep working. Set `api.legacyPayload` to `false` only after all callers use the envelope.

Field and section API proxies require read permission for `GET`/`HEAD` and update permission for mutating methods. A custom non-mutating `POST` route can opt into read authorization explicitly:

```php
[
  'pattern' => 'search',
  'method' => 'POST',
  'blueprintAreasAccess' => 'read',
  'action' => function () {
    // Return read-only data.
  },
]
```

Use this override only for routes that cannot change model or external state.

## Buttons

Customize the view header buttons via the `buttons` property:

```yml
# site/blueprints/areas/settings.yml
title: Settings
icon: cog

buttons:
  - preview
  - open

tabs:
  # ...
```

### Available buttons

| Name        | Description                              |
| ----------- | ---------------------------------------- |
| `languages` | Language switcher (multi-language sites) |
| `open`      | Open model URL in new tab                |
| `preview`   | Preview draft changes                    |
| `settings`  | Model settings dialog                    |
| `status`    | Page status (pages only)                 |
| `versions`  | Version history                          |

> [!NOTE]
> When no `buttons` are defined, only the language switcher appears on multi-language sites.

### Disabling buttons

Set `buttons` to `false` to hide all buttons:

```yml
buttons: false
```

## Options

Configure via `site/config/config.php`:

```php
return [
  'grommasdietz.blueprint-areas' => [
    'panel' => [
      // Show/hide all auto-registered menu entries
      'enabled'    => true,

      // Show a numeric badge instead of a dot on menu items
      'badgeCount' => false,

      // Optional Panel area ID/URL prefix; empty preserves existing URLs
      'areaPrefix' => '',
    ],

    // Override the blueprint directory
    'blueprints.root' => kirby()->root('blueprints') . '/areas',

    'api' => [
      // Accept direct field maps as well as the canonical values envelope
      'legacyPayload' => true,

      // Reject excessively nested value payloads
      'maxPayloadDepth' => 32,

      // Optional encoded payload limit in bytes
      'maxPayloadBytes' => null,
    ],
  ]
];
```

---

Next: Continue with [Architecture](./architecture.md)
