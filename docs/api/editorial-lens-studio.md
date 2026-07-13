# Editorial Lens Studio

Editorial Lens Studio provides an authorised workflow for creating, revising, previewing, activating and deprecating named graph lenses.

## Authorisation

Studio requests require both headers:

```text
X-WonderOS-Editor-Key: <EDITORIAL_API_KEY>
X-WonderOS-Editor: editor@example.com
```

The shared key is a Genesis boundary, not the final identity system. Production deployments should provide it through secret management and rotate it regularly.

## Create a draft lens

```http
POST /v1/editorial-lenses
```

```json
{
  "slug": "sacred-landscape",
  "name": "Sacred Landscape",
  "description": "Place, symbolism and historic influence connections.",
  "types": ["located_in", "symbolises", "influenced"],
  "statuses": ["approved"],
  "minimum_confidence": 0.7
}
```

New lenses always begin with `draft` status and revision `1`.

## Revise a lens

```http
PUT /v1/editorial-lenses/sacred-landscape
```

```json
{
  "name": "Sacred Landscapes",
  "description": "Approved place, symbolism and influence connections.",
  "types": ["located_in", "symbolises", "influenced", "associated_with"],
  "statuses": ["approved"],
  "minimum_confidence": 0.75,
  "expected_revision": 1
}
```

A revision returns the lens to `draft` and increments its revision. A stale `expected_revision` is rejected.

## Preview before activation

```http
POST /v1/editorial-lenses/sacred-landscape/preview
```

```json
{
  "root_wonder_id": "WND-ENT-000001",
  "depth": 2,
  "max_nodes": 100
}
```

Preview uses the stored draft filter without exposing the lens through public graph traversal.

## Activate

```http
POST /v1/editorial-lenses/sacred-landscape/activate
```

```json
{
  "expected_revision": 2
}
```

Only active lenses appear in `GET /v1/editorial-lenses` and can be applied through `?lens=`.

## Deprecate

```http
POST /v1/editorial-lenses/sacred-landscape/deprecate
```

```json
{
  "expected_revision": 3
}
```

Deprecated lenses remain in history but cannot be applied to new graph requests.

## Revision history

```http
GET /v1/editorial-lenses/sacred-landscape/revisions
```

Each entry records the complete lens snapshot, revision number, editor identity and timestamp.
