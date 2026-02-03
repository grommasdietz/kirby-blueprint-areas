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

Or restrict via user role blueprints:

```yml
permissions:
  areas:
    home: false
```

> [!NOTE]
> Role permissions always win over area access rules.

> [!IMPORTANT]
> Kirby Blueprint Areas still enforces the underlying model permissions (for example, pages must be updatable for writes).

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
  'grommasdietz.kirby-blueprint-areas' => [
    'panel' => [
      // Show/hide all auto-registered menu entries
      'enabled'    => true,

      // Show a numeric badge instead of a dot on menu items
      'badgeCount' => false,
    ],

    // Override the blueprint directory
    'blueprints.root' => kirby()->root('blueprints') . '/areas',
  ]
];
```

---

Next: Continue with [Architecture](./architecture.md)
