# AGENTS.md

Agent guide for the Kirby Blueprint Areas plugin (grommasdietz/blueprint-areas). Use this as the quick, canonical workflow overview; detailed rules live in the referenced docs.

---

## Project overview

- Plugin entry: `index.php` registers `App::plugin('grommasdietz/blueprint-areas', ...)`.
- PHP code lives under `lib` (namespace `GrommasDietz\\Areas\\`).
- Panel source lives under `src` (including `src/components/**`) and is built with `kirbyup` into `index.js`/`index.css`.
- Playground: `playground` is the self-contained Kirby site for integration and browser tests.

---

## Build and test commands

- Install PHP deps and tools: `composer run setup`
- Install JS deps and Playwright: `pnpm run setup`
- Rebuild Panel assets after UI changes: `pnpm build` (commit `index.js` and `index.css`).
- Lint Panel code: `pnpm lint`
- Playwright browser tests (Panel behavior changes): `pnpm test:browser`
- PHP tests: `composer test`
- PHP static analysis (when touching PHP logic): `composer psalm`
- Full PHP sweep: `composer run verify`
- Full JS sweep: `pnpm run verify`

---

## Shipping rules

- Keep it ready-to-install. ZIP/submodule/Composer installs must work without running `composer` or `npm`.
- Keep committed build outputs (`index.js`, `index.css`); do not edit compiled assets by hand.
- Don't commit local artifacts. No `.DS_Store`, no local symlinks, no runtime data from `playground/site/*`, no caches.

---

## References

- Contributor workflow: `CONTRIBUTING.md`
- Documentation rules: `docs/contributions/documentation.md`
- Style guide: `STYLE_GUIDE.md`
- Security policy: `SECURITY.md`
- Release process: `docs/maintenance/workflow.md`
