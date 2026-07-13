# Claims, sources and evidence

Genesis-020 separates canonical subjects from the statements made about them and the material used to support those statements.

## Model

- An **entity** is the canonical subject, such as `WND-ENT-000001` Barn Owl.
- A **claim** is a discrete statement about that subject, such as “Barn Owls hunt mostly at night”.
- A **source** is an independently reusable publication, archive, interview, dataset or other reference.
- **Evidence** links one source to one claim with a locator, optional excerpt, stance and strength.

Evidence stances are `supports`, `contradicts` and `contextualises`. This prevents WonderOS from treating every citation as unquestioning support.

## Routes

- `POST /v1/sources`
- `GET /v1/sources/{sourceWonderId}`
- `POST /v1/entities/{entityWonderId}/claims`
- `GET /v1/entities/{entityWonderId}/claims`
- `POST /v1/claims/{claimWonderId}/evidence`
- `GET /v1/claims/{claimWonderId}/evidence`

Writes require `knowledge.contribute`. Reads remain available through the knowledge API.

## Audit events

- `source.created`
- `claim.created`
- `evidence.linked`

## Example

1. Create the source `WND-SRC-000001`.
2. Create claim `WND-CLM-000001` for Barn Owl.
3. Link the source to the claim with `stance=supports`, `locator=pp. 42–44` and an evidence strength.

Claims begin as drafts and can later move through review, approved, disputed or archived states. Claim lifecycle editing and source-quality assessment remain future work.