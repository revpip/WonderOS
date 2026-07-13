# Account administration

Administrators manage WonderOS accounts at `/accounts.html`.

## Capabilities

- create Viewer, Researcher, Editor and Administrator accounts;
- change a user's role or active status;
- suspend an account and immediately revoke all active sessions;
- revoke all sessions without suspending the account;
- inspect the immutable account-activity log.

## Safety rules

- only Administrators can access the administration API;
- an Administrator cannot demote or suspend their own account;
- use normal logout to revoke your own current session;
- account changes are recorded with actor, subject, action, context and timestamp;
- activity records are not editable through the public API.

## Routes

- `PUT /v1/users/{uuid}`
- `POST /v1/users/{uuid}/sessions/revoke`
- `GET /v1/account-activity`

Existing `GET /v1/users` and `POST /v1/users` routes remain Administrator-only.