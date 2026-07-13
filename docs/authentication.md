# WonderOS authentication and roles

WonderOS uses database-backed user accounts and opaque bearer sessions.

## Bootstrap the first administrator

After applying `0007_users_and_sessions.up.sql`:

```bash
php tools/create-admin.php admin@example.com "Admin Name" "a-password-of-12+-characters"
```

Passwords are stored with PHP `password_hash`. Raw session tokens are returned once at login and only SHA-256 hashes are stored in PostgreSQL. Sessions expire after eight hours and can be revoked with logout.

## Roles

| Role | Permissions |
|---|---|
| Viewer | Read canonical knowledge |
| Researcher | Read and contribute knowledge |
| Editor | Researcher permissions plus editorial lens management |
| Administrator | Editor permissions plus user administration |

## Session API

- `POST /v1/auth/login`
- `POST /v1/auth/logout`
- `GET /v1/auth/me`

Authenticated requests use:

```http
Authorization: Bearer <session-token>
```

Administrators can list and create accounts through `GET /v1/users` and `POST /v1/users`. New passwords require at least 12 characters.

The shared `EDITORIAL_API_KEY` is no longer used by the Lens Studio. Console session tokens remain in browser session storage and disappear when the session closes.
