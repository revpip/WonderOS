# Secure invitations

Administrators onboard people through one-time invitations rather than temporary passwords.

## Lifecycle

1. An Administrator creates an invitation with email, display name and role.
2. WonderOS returns the raw token once and stores only its SHA-256 hash.
3. The invitation expires after seven days.
4. The recipient chooses a password of at least 12 characters.
5. Acceptance creates the user and marks the invitation used in one transaction.
6. Used, expired or revoked tokens cannot be accepted.

## Routes

- `GET /v1/invitations` — Administrator-only invitation history.
- `POST /v1/invitations` — create a one-time invitation.
- `POST /v1/invitations/{uuid}/revoke` — revoke a pending invitation.
- `POST /v1/invitations/accept` — public one-time acceptance route.

The acceptance page is available at `apps/console/accept-invitation.html?token=...`.

## Operational boundary

Genesis returns the invitation link to the Administrator for manual delivery. Transactional email delivery, domain allow-lists, invitation resend, throttling and abuse monitoring belong to later iterations.