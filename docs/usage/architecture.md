# Architecture

Kirby Blueprint Areas uses the same blueprint shape as core views. This page explains how values are stored and how drafts, locks, and badges behave.

> [!IMPORTANT]
> Custom sections may work only when they register a Panel component and expose API routes.

## Storage

Values are stored on the resolved model using the field keys. Unpublished changes are stored on the model’s `changes` version (like drafts in Kirby’s Panel).

> [!IMPORTANT]
> If two areas point to the same model and reuse the same field keys, they will read and write the same values.

## Routes

The blueprint filename becomes the area ID and URL segment in the Panel.

> [!IMPORTANT]
> If the filename collides with a core Kirby area ID (for example `site` or `users`), the area is skipped to avoid overriding built‑in views.

## Drafts and locks

Areas share the same `changes` version and content lock as the resolved model. If the model is locked (for example by another user editing it), the area will reflect that lock as well.

## Badges

Badges are rendered on each area’s menu entry when unpublished changes exist on any field used by the area. They remain visible across reloads and can reflect changes made from other views/areas that write to the same model.

---

Next: Continue with [Contributions](../contributions/index.md)
