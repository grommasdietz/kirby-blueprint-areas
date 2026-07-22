# Architecture

Kirby Blueprint Areas uses the same blueprint shape as core views. This page explains how values are stored and how drafts, locks, and badges behave.

> [!IMPORTANT]
> Custom sections may work only when they register a Panel component and expose API routes.

## Storage

Values are stored on the resolved model using the field keys. Unpublished changes are stored on the model’s `changes` version (like drafts in Kirby’s Panel).

Draft and publish operations only merge the submitted, editable fields from the current area. Discard removes all fields declared by that area from the model’s `changes` version. In every case, unrelated latest content and unrelated pending changes on the same model remain untouched.

> [!IMPORTANT]
> If two areas point to the same model and reuse the same field keys, they will read and write the same values.

## Routes and area IDs

By default, the blueprint filename becomes the logical area ID and Panel URL segment. Existing filenames, role permissions, URLs and bookmarks therefore remain unchanged.

The plugin skips IDs that collide with Kirby core areas or another registered Panel area. Because plugin registration is load-order dependent, installations with many custom areas can opt into a stable prefix:

```php
'grommasdietz.blueprint-areas' => [
  'panel' => [
    'areaPrefix' => 'blueprint-areas-',
  ],
],
```

A blueprint named `settings.yml` then keeps the logical API ID `settings`, while its Panel area ID and URL become `blueprint-areas-settings`. Legacy `permissions.areas.settings` role rules are still evaluated.

Area names are resolved from the discovered YAML-file allowlist. Request parameters cannot be converted directly into arbitrary filesystem paths.

## Authorization

All plugin API routes require Kirby authentication. Each view and read endpoint additionally requires:

- the registered Panel area permission;
- the area blueprint's `options.access` rule;
- the resolved model's `access` permission;
- the resolved page's `read` permission for page-backed areas.

Writes and mutating field/section proxy routes additionally require the model's `update` permission. Areas without update permission render without save controls and with disabled field inputs. Proxy routes are matched before dispatch; unsupported paths and methods are rejected rather than forwarded blindly.

## Request data

Mutation endpoints accept `{ "values": { ... } }`. Values are constrained to field names declared by the resolved area blueprint before they reach Kirby's form and model update APIs. Legacy direct field maps remain available by default for compatibility and can be disabled with `api.legacyPayload`.

## Drafts and locks

Areas share the same `changes` version and content lock as the resolved model. If the model is locked (for example by another user editing it), the area will reflect that lock as well.

## Badges

Badges are rendered on each area’s menu entry when unpublished changes exist on any field used by the area. They remain visible across reloads and can reflect changes made from other views/areas that write to the same model.

---

Next: Continue with [Contributions](../contributions/index.md)
