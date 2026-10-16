# Kirby Blueprint Areas

Add custom Panel areas via blueprints on Kirby CMS.

![Cover image showing an example of the plugin in use](/.github/assets/hero-image.png)

## Requirements

- Kirby 5.5.2+
- PHP 8.2+

## Installation

```bash
composer require grommasdietz/kirby-blueprint-areas
```

> [!TIP]
> For a manual install, download the **Source code (zip)** archive of an immutable GitHub release tag and copy it to `site/plugins/kirby-blueprint-areas`.

## Quickstart

Create blueprints in `site/blueprints/areas`. Define `title`, `icon`, and your desired content:

```yml
# site/blueprints/areas/namespace.yml
title: Namespace
icon: box

tabs:
  content:
    label: Content
    columns:
      - width: 1/1
        sections:
          settings:
            type: fields
            fields:
              headline:
                label: Headline
                type: text
```

Each blueprint will render as an own area. Each area saves content to site model by default. Optionally change resolved model (like a page) with [query](docs/usage/index.md#query) or restrict users [access](docs/usage/index.md#access-control).

See the [options reference](docs/usage/index.md#options) for menu settings, model access and API configuration.

### Documentation

Full reference for [usage](docs/usage/index.md), [contributions](docs/contributions/index.md) and [maintenance](docs/maintenance/index.md) lives in [documentation](docs/index.md).

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.

---

## Security

See [SECURITY.md](SECURITY.md) for security policies and reporting vulnerabilities.

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidance and expectations.

---

## License

[MIT](LICENSE.md) © 2026 Grommas Dietz
