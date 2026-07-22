# Kirby internals

This page lists the Kirby internals that Kirby Blueprint Areas relies on. These are not
part of the public API, so check them when updating Kirby.

---

## Panel internals

- `panel.content` manager: `updateLazy`, `discard`, `publish`, `env`, and
  `request` are used to drive drafts and saving. A scoped compatibility adapter
  intercepts only API paths beginning with this plugin's blueprint prefix and
  forwards every other request to Kirby's original implementation.
  - Core reference: `panel/src/panel/content.js`
- `panel.view.props.versions` and `panel.view.props.lock` are read to compute
  diffs and lock state in `AreasView`.
  - Core reference: `panel/src/components/Views/ModelView.vue`
- `panel.menu.set` is wrapped to keep menu badges when Kirby rebuilds the menu.
  - Core reference: `panel/src/panel/menu.js` (implementation detail)
- `panel.view.set` is wrapped to reapply menu badges after view changes.
  - Core reference: `panel/src/panel/view.js` (implementation detail)
- `panel.events` events are used to resync badges and shortcuts:
  `content.save`, `content.publish`, `content.discard`, `keydown.cmd.s`.

## API routing assumptions

Kirby core registers `(:all)/changes/*` API routes before plugin routes, which
causes `Find::parent()` to reject plugin-specific paths. We therefore route area
saves to custom endpoints (`.../save`, `.../publish`, `.../discard`) instead of
relying on `.../changes/*`. Do not register compatibility aliases below that
reserved suffix: Kirby's core wildcard routes will always capture them first. The
adapter sends the canonical `{ values: {...} }` payload while the PHP layer keeps
a configurable legacy payload fallback.

Field and section APIs are exposed through Kirby's own nested API router. The
plugin preflights the exact path and method first, applies read/update model
authorization, and then dispatches the matched route. Mutating methods require
update permission unless the route explicitly declares
`blueprintAreasAccess: read`.

- Core reference: `config/api/routes/changes.php`
- Model resolution: `src/Cms/Find.php`

## Content/lock behavior

Locks are computed from the `changes` version and are per-model (not per view).
If multiple areas resolve to the same model, they share the same lock state.

- Core reference: `src/Content/Lock.php`, `src/Content/Version.php`

## Upgrade checklist

When updating Kirby, verify:

- `panel/src/panel/content.js` still exposes the same `request` contract.
- `panel/src/components/Views/ModelView.vue` still reads `props.versions` and
  `props.lock` the same way.
- `config/api/routes/changes.php` route order and patterns are unchanged.
- `src/Api/Api.php` and `src/Http/Router.php` still preserve nested route
  attributes and method matching used by proxy authorization.
- `src/Cms/ModelPermissions.php` still exposes `access`, `read` and `update`
  through `can()` with the current blueprint/role precedence.
- `src/Content/Lock.php` and `src/Content/Version.php` lock semantics are
  compatible with our `changes`-based drafts.

---

Next: Continue with [Documentation](./documentation.md)
