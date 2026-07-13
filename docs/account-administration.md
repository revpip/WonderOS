# Account Administration

Administrators manage WonderOS colleagues at `apps/console/accounts.html`.

## Capabilities

- create an account with a temporary password;
- assign Viewer, Researcher, Editor or Administrator roles;
- suspend or reactivate an account;
- revoke all active sessions immediately;
- review the thirty most recent sessions for an account.

## API

- `GET /v1/users`
- `POST /v1/users`
- `PUT /v1/users/{uuid}`
- `POST /v1/users/{uuid}/sessions`
- `GET /v1/users/{uuid}/activity`

All routes require an Administrator bearer session. Suspending an account revokes its open sessions in the same application operation. An Administrator cannot suspend the account represented by their current session.

## Operational notes

Account deletion is intentionally unavailable during Genesis. Suspending preserves attribution and audit history while removing access. Password resets, invitations, MFA and detailed event auditing remain later security work.