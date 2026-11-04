# Setup

This plugin uses the `playground` site for integration and browser tests.

---

## Composer

Install Composer dependencies for the repo and the playground:

```bash
composer run setup
```

Setup provisions `KIRBY_CONTENT_SALT` and `KIRBY_COOKIE_KEY` in the ignored repository-root `.env`, outside the playground document root. The playground also provisions missing or empty values on first boot. Values are independent, survive playground resets and are never rotated automatically. Existing comments and unrelated settings are preserved. The two keys accept literal quoted or unquoted values of at least 32 characters; process environment values take precedence. Invalid or duplicate assignments raise an error. The repository root must be writable for initial generation.

The plugin runtime and contributor toolchain require PHP 8.3 or newer. The root
dependencies are resolved against PHP 8.3 (`config.platform`). CI runs the runtime
smoke test and the full suite on PHP 8.3, 8.4 and 8.5.

The root dependency graph contains PHPUnit, Psalm, PHP CS Fixer and Kirby for
self-contained PHP quality checks. The separate playground graph provides the
disposable application used by runtime and browser tests.

---

## Node

Install Node dependencies and the Playwright Chromium browser:

```bash
pnpm run setup
```

On Linux CI, install Chromium and its operating-system packages with `pnpm exec playwright install --with-deps chromium`.

---

## VS Code and Intelephense

Open this plugin directory itself as a VS Code workspace folder, or add it as
one folder in a multi-root workspace. After `composer run setup`, the root
`vendor/` directory contains Kirby's PHP classes for static analysis.

If Kirby classes still appear as undefined, run **Intelephense: Clear Cache**
and reload the VS Code window. The repository settings point Intelephense to
the root and playground Kirby installations, but nested `.vscode` settings are
only applied when this repository is an active workspace folder.

---

Next: Continue with [Structure](./structure.md)
