# Agent guide

Repository guide for the Kirby Blueprint Areas plugin (`grommasdietz/blueprint-areas`).

## Blueprint Areas rules

- **Keep it ready-to-install.** Composer and GitHub tag-archive installs must work without running `composer` or `npm`.
- Plugin entry: `index.php` registers `App::plugin('grommasdietz/blueprint-areas', ...)`. PHP code lives under `lib` (namespace `GrommasDietz\\Areas\\`).
- Panel source lives under `src` (including `src/components/**`) and builds with `kirbyup` into `index.js`/`index.css`.
- `playground` is the self-contained Kirby site for integration and browser tests.
- Focused commands: `pnpm lint`, `pnpm run test:browser`, `composer test` and `composer psalm`.
- Release process: [docs/maintenance/workflow.md](docs/maintenance/workflow.md). Contributor workflow: [CONTRIBUTING.md](CONTRIBUTING.md). Security policy: [SECURITY.md](SECURITY.md).

<!-- shared:kirby-plugin-workflow — identical in every Grommas Dietz Kirby plugin repository. Change it in kirby-plugin first, then copy it unchanged. -->

## Shared workflow

- Do not commit local state: credentials, environment files, accounts, sessions, caches, databases, test reports, local plugin links or installed dependencies.
- Committed Panel assets such as `index.js` and `index.css` are build output. Edit `src/`, rebuild with `pnpm run build` and commit the result.
- Use Conventional Commits and focused diffs. Keep source, tests, translations, documentation and generated assets aligned.
- Keep public classes, options, routes, commands, stored data and blueprint handles stable unless a change intentionally breaks them.
- Add regression coverage for fixes. Keep fixtures deterministic and fictional, and never contact real services in default tests.
- Panel copy uses sentences or commas. Avoid semicolons and centered dots as separators.
- Prefer Kirby's built-in Panel icons. Add namespaced Remix Icon glyphs only when Kirby has no suitable icon.
- Install with `composer run setup` and `pnpm run setup`. Before handing over changes, run `composer run verify` and `pnpm run verify`. CI runs the same two commands through the shared `kirby-plugin` workflow in `grommasdietz/.github`, so add new checks to them, not to workflow files. Use the focused lint, Psalm, test and build commands while iterating.
- Keep the README a short user-facing quickstart. Follow [STYLE_GUIDE.md](STYLE_GUIDE.md) and the [documentation guidance](docs/contributions/documentation.md).
- This file is canonical. Assistants such as Copilot read it directly, so do not add per-assistant instruction files.

<!-- /shared:kirby-plugin-workflow -->
