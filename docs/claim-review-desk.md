# Claim Review Desk

The Claim Review Desk gives Editors and Administrators a visual queue for draft, review and disputed claims.

## API

`GET /v1/claim-review-queue?statuses=draft,review,disputed&limit=100`

The route requires an authenticated account with `editorial.manage`. Results include the canonical entity name, claim status, current revision and evidence count. Disputed claims are shown first, followed by claims already in review and then drafts.

Existing review routes power the workspace:

- `GET /v1/claims/{id}`
- `GET /v1/claims/{id}/evidence`
- `GET /v1/claims/{id}/revisions`
- `PUT /v1/claims/{id}`
- `POST /v1/claims/{id}/review`

## Console

Open `http://localhost:8081/claim-review.html` after signing in as an Editor or Administrator.

Editors can compare the current wording with revision history, inspect supporting or contradicting evidence, save a revision, approve a claim, mark it disputed or return it to research. Every mutation sends the current `expected_revision`, so stale browser state cannot overwrite newer editorial work.

## Boundaries

This Genesis desk does not yet include assignment, comments, bulk review, keyboard shortcuts, saved filters or side-by-side textual diff highlighting. Evidence is displayed as durable current context rather than snapshotted inside each historical revision.
