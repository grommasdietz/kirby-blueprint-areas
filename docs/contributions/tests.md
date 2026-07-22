# Tests

Run tests after PHP or Panel changes and add coverage for every bug fix and
security boundary.

> [!IMPORTANT]
> Complete the [Setup](./setup.md) steps first.

## Complete verification

After installing both dependency graphs, run the complete repository gate:

```bash
composer run setup
pnpm run setup
pnpm run verify:all
```

The command prints one concise status per check, stops at the first failure,
and shows the captured output only for that failed check. Browser tests use the
compact dot reporter. Open the full HTML report with
`pnpm run test:browser:report`, or expose the verbose list reporter and PHP
server logs with `pnpm run test:browser:debug`.

Do not enable `set -e` in an interactive shell. The verification command exits
only its child process and returns control to the terminal.

## PHP

Run all PHP suites:

```bash
composer run verify
```

Target individual checks when debugging:

```bash
composer run lint
composer run psalm
composer run test:unit
composer run test:integration
composer run test:smoke
composer run test:coverage
composer run release:check
```

Use the shared `tests/TestCase.php` base class to boot Kirby with the playground
roots. It wraps `tests/Support/TestEnvironment.php` and lets tests override
configuration values when needed.

Test-only plugin fixtures live in `tests/Fixtures/plugins`. Pass their directory
names through `testPluginsBefore` or `testPluginsAfter` when booting the test
environment. The harness creates uniquely named, ordered wrapper plugins and
lets one Kirby constructor discover them through the normal plugin loader. A
fixture that must affect Blueprint Areas while its dynamic areas are registered
must apply that test state immediately when its `testPluginsBefore` entry loads;
see the competing-area fixture for the collision-test pattern.

Proxy integration tests that register synthetic field and section component types
run in separate PHP processes. Kirby stores these component registries statically,
so process isolation prevents fixture definitions from leaking across data-provider
cases without changing the production plugin lifecycle.

### Kirby compatibility matrix

CI runs the PHPUnit suites against these Kirby constraints on PHP 8.3:

- `5.2.*`, the minimum supported release line;
- `5.4.*`, where native Site access behavior changed;
- the repository's locked Kirby version;
- the latest version allowed by `^5.2`.

Keep version-specific branches covered by this matrix. Do not rely only on the
locked playground dependency when introducing compatibility code.

## Panel and browser checks

The Playwright global setup seeds the playground automatically. For a targeted
manual seed, use the canonical Composer command `composer run playground:seed`;
`pnpm run playground:seed` is a delegating convenience alias.

```bash
pnpm run build:check
pnpm run lint
pnpm run test:bundle
pnpm run test:archive
pnpm run docs:verify
pnpm run test:hygiene
pnpm run test:browser
pnpm run test:browser:api-slug
```

`build:check` rebuilds the compiled Panel output, compares it with the committed
files and restores the original working tree before reporting success or failure.
It fails when committed files are stale. Playwright starts one repo-specific PHP server, keeps normal server
traffic quiet, and preserves traces, screenshots and video on failure.

The dedicated API-slug command starts a second isolated server with
`KIRBY_API_SLUG=control`. The ordinary browser suite excludes this spec because
it needs that dedicated environment, instead of reporting an intentional skip.
The dedicated run verifies that Panel navigation, field hydration and content
requests follow Kirby's configured API endpoint rather than a hardcoded `/api`
path.

### Panel users

Global setup creates these temporary users:

- `admin@kirby-blueprint-areas.test` / `playwright`;
- `editor@kirby-blueprint-areas.test` / `playwright`;
- `readonly@kirby-blueprint-areas.test` / `playwright`.

The editor and read-only roles are defined in
`playground/site/blueprints/users`. They cover menu authorization, direct URL
denials, readable-but-not-updatable views, disabled section controls and API
write rejection. Override the admin credentials with `KIRBY_USER_EMAIL` and
`KIRBY_USER_PASSWORD` when needed.

### Browser coverage expectations

Browser tests should verify behavior that direct PHP calls cannot prove:

- canonical `{ values: ... }` draft and publish requests;
- publish and discard lifecycle behavior;
- read-only fields, sections and keyboard shortcuts;
- authenticated API status codes and CSRF rejection;
- isolation of the global Panel content adapter from normal Kirby pages;
- custom API-slug compatibility.

Keep network assertions scoped to exact endpoint suffixes. Do not silently skip
missing controls or menu links; a missing expected element is a test failure.

### Playground state

Browser tests use canonical copies of `site.de.txt` and `site.en.txt`, then
restore the exact tracked content after the suite. Runtime accounts, sessions,
cache and media are removed without deleting Composer-installed plugin links.

The playground permanently contains exactly five area blueprints, each with a
separate purpose:

- `buttons` — custom Panel header buttons and blueprint-level access rules;
- `empty` — the intentional empty-area state;
- `fields` — site-backed native fields and core sections;
- `home` — a page-backed area resolved with `query` and an extended tab;
- `translations` — a translated area title plus visibly different seeded content values for English and German.

PHP-only collision, payload and proxy fixtures belong in `tests/.blueprints` or
`tests/Fixtures/plugins`; they must never be written into the permanent
playground. Keep any additional files under `playground/site/**` and
`playground/content/**` minimal and focused on browser-visible behavior.

Next: Continue with [Kirby internals](./kirby-internals.md)
