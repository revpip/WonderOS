# Canonical knowledge audit events

Genesis-019 extends the immutable Activity Ledger beyond account administration.

## Captured events

| Event | Subject | Trigger |
|---|---|---|
| `entity.created` | Entity Wonder ID | Canonical entity created |
| `entity.alias_added` | Entity Wonder ID | Alternative name attached |
| `relationship.created` | Relationship UUID | Canonical graph edge created |
| `editorial_lens.created` | Lens slug | Draft lens created |
| `editorial_lens.revised` | Lens slug | Lens definition revised |
| `editorial_lens.activated` | Lens slug | Lens activated |
| `editorial_lens.deprecated` | Lens slug | Lens deprecated |

## Attribution

Canonical entity, alias and relationship writes require an authenticated Researcher, Editor or Administrator with `knowledge.contribute`.

Editorial lens lifecycle changes require an Editor or Administrator with `editorial.manage`.

The authenticated account is always used as the audit actor. Client-supplied editor names are not trusted for attribution.

## Transaction boundary

The gateway records an event only after the delegated API returns a successful 2xx result. Validation failures, duplicate conflicts, stale revisions and denied permissions do not produce false success events.

The audit record includes useful context such as Wonder IDs, relationship type, confidence, lens revision and lifecycle status. It excludes request passwords, bearer tokens, session hashes and other credentials.

## Search examples

```http
GET /v1/audit-events?action=entity.created
GET /v1/audit-events?subject_type=entity&subject_id=WND-ENT-000001
GET /v1/audit-events?action=editorial_lens.activated
```

Only Administrators can query the Activity Ledger.
