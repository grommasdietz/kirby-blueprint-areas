# Kirby internals

This page lists the Kirby internals that Kirby Blueprint Areas relies on. These are not
part of the public API, so check them when updating Kirby.

---

## Panel internals

- `panel.content` manager: `updateLazy`, `discard`, `publish`, `env`, and
  `request` are used/overridden to drive drafts and saving.
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
relying on `.../changes/*`.

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
- `src/Content/Lock.php` and `src/Content/Version.php` lock semantics are
  compatible with our `changes`-based drafts.

---

Next: Continue with [Documentation](./documentation.md)
