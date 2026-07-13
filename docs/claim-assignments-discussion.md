# Claim assignments and discussion

Genesis-023 adds accountable collaboration around canonical claims.

## Routes

- `GET /v1/my-assignments`
- `GET /v1/claims/{claimWonderId}/assignments`
- `POST /v1/claims/{claimWonderId}/assignments`
- `PUT /v1/claim-assignments/{assignmentUuid}`
- `GET /v1/claims/{claimWonderId}/comments`
- `POST /v1/claims/{claimWonderId}/comments`

Assignments are either `research` or `review`, may include a due date and move through `open`, `completed` or `cancelled`.

Comments support an optional parent UUID for threaded replies and a JSON mention list. Discussion records are attributable and remain attached to the claim.

## Permissions

- Reading personal assignments requires an authenticated account.
- Creating or changing assignments requires `editorial.manage`.
- Reading and adding claim discussion requires `knowledge.contribute`.

## Audit actions

- `claim.assigned`
- `claim.assignment_updated`
- `claim.comment_added`

## Console

Open `http://localhost:8081/claim-collaboration.html` after signing in.

## Boundaries

Genesis does not yet send email or in-app notifications for mentions and due dates. Comment editing, deletion, resolution, rich text and granular assignment ownership remain later work.
