# WonderOS Audit Ledger

Genesis-018 introduces an append-only record of consequential account and security actions.

## Captured events

- `auth.login`
- `auth.logout`
- `user.created`
- `user.updated`
- `user.sessions_revoked`

Each event records the actor, action, subject, human-readable summary, timestamp and structured metadata. Passwords, bearer tokens and token hashes must never be placed in metadata.

## Immutability

PostgreSQL triggers reject `UPDATE` and `DELETE` operations against `wonder_audit_events`. Corrections are represented by a new event rather than rewriting history.

## Search

Administrators can use:

`GET /v1/audit-events`

Optional exact-match filters are `actor_email`, `action`, `subject_type`, `subject_id` and `limit` (maximum 250).

The Console view is available at `http://localhost:8081/audit.html`.

## Retention

No automatic deletion policy is included in Genesis. A formal retention, export and legal-hold policy must be agreed before production deployment.