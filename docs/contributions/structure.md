# Plugin structure

This page separates runtime files from development tooling.

---

## Runtime essentials

These files are required at runtime and must stay in release archives:

- `index.php` — Plugin entry point
- `index.js` / `index.css` — Compiled Panel assets (keep committed)
- `lib` — PHP source (`GrommasDietz\\Areas\\`)

---

## Development‑only

The following live in the repository but are excluded from release archives:

- `src` — Panel source for kirbyup
- `playground` — Local Kirby site for integration and browser tests
- `tests` — PHPUnit and Playwright suites
- `tools` — Local helper scripts
- `docs`, `CONTRIBUTING.md`, `STYLE_GUIDE.md`, `SECURITY.md` — Documentation and policies
- Tooling config (ESLint, PostCSS, Psalm, PHPUnit, Playwright)

Packaging rules for release archives live in `.gitattributes`.

---

## Psalm configuration

Psalm analyzes the committed runtime PHP listed in `psalm.xml.dist`: `lib/` and
`index.php`. The playground, tests, generated assets and development helpers are
validated by their dedicated checks instead.

When adding a new runtime PHP directory outside `lib/`, add it to
`<projectFiles>`. Add a narrowly scoped issue-handler suppression only when a
real Kirby framework pattern cannot be expressed accurately in Psalm; do not
add placeholder directories or broad suppressions pre-emptively.

YAML blueprints, static assets and JavaScript source do not belong in Psalm’s
PHP project file list.

---

Next: Continue with [Workflow](./workflow.md)
