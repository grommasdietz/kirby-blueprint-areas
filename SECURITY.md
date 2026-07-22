# Security Policy

We take the security of Kirby Blueprint Areas seriously.

## Supported Versions

Only the latest published release receives security updates. Please upgrade to the most recent tag before requesting a fix.

## Authorization model

All plugin API routes use Kirby's authenticated API layer. Access to an area is additionally constrained by the role's Panel area permission, the area's `options.access` rule and the resolved model's native permissions. Read-only users can load permitted areas, while saves, draft changes, discards and mutating field or section routes require the model's `update` permission.

Custom field and section API routes should use read-safe HTTP methods for non-mutating operations. A non-mutating `POST` route must explicitly declare `blueprintAreasAccess: read`; otherwise it is treated as an update operation.

## Reporting a Vulnerability

Email `security@grommas-dietz.com` with the subject line `Kirby Blueprint Areas: Security report`. Include:

- [ ] A clear description of the issue and potential impact
- [ ] Steps to reproduce (proof of concept or fixture payloads)
- [ ] Suggested mitigations, if available

> [!NOTE]
> We aim to acknowledge new reports within **48 hours** and provide a status update within **5 business days**. Critical issues receive priority handling.
