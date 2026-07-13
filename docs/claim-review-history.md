# Claim review and revision history

WonderOS preserves every accepted change to a claim as an immutable revision.

## Routes

- `GET /v1/claims/{claimWonderId}` — current claim
- `PUT /v1/claims/{claimWonderId}` — revise wording, type or confidence
- `POST /v1/claims/{claimWonderId}/review` — move a claim through review states
- `GET /v1/claims/{claimWonderId}/revisions` — newest-first revision history

Every mutation requires `expected_revision`. A stale revision returns `409 CLAIM_REVISION_CONFLICT`.

## Lifecycle

`draft → review → approved`

Editors may also mark a claim `disputed` or `archived`. Researchers can revise claims, but review status changes require `editorial.manage`.

## Example review

```json
{
  "expected_revision": 2,
  "status": "approved",
  "confidence": 0.9,
  "change_note": "Approved after checking two independent historical sources."
}
```

## Audit actions

- `claim.revised`
- `claim.reviewed`

The ledger stores revision, status, confidence and the non-secret change note. Historical wording is stored in `wonder_claim_revisions` and is never overwritten.

## Boundary

Evidence links remain independently preserved. This iteration does not yet snapshot the full evidence set inside each claim revision; evidence has its own durable records and timestamps.