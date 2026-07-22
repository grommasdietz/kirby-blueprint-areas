# Setup

This plugin uses the `playground` site for integration and browser tests.

---

## Composer

Install Composer dependencies for the repo and the playground:

```bash
composer run setup
```

The plugin runtime supports PHP 8.2 and newer. The root contributor toolchain is
resolved against PHP 8.3 (`config.platform`) because PHPUnit 12 and the complete
quality suite require PHP 8.3 or newer. CI still runs the runtime smoke test on
PHP 8.2 and the full suite on supported newer versions.

The root dependency graph contains PHPUnit, Psalm, PHP CS Fixer and Kirby for
self-contained PHP quality checks. The separate playground graph provides the
disposable application used by runtime and browser tests.

PHP CS Fixer may print an informational warning when it runs on PHP 8.3 while
the package declares PHP 8.2 as its minimum runtime. This is expected in the
contributor setup; CI and Psalm continue to validate the PHP 8.2 target.

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
