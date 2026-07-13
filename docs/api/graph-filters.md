# Graph filters

`GET /v1/entities/{wonderId}/graph` supports editorial lenses without changing the stored knowledge graph.

## Parameters

| Parameter | Format | Purpose |
| --- | --- | --- |
| `types` | comma-separated relationship types | Include only selected canonical relationship meanings. |
| `statuses` | comma-separated statuses | Include only `draft`, `review`, `approved`, or `archived` relationships. |
| `min_confidence` | number from 0 to 1 | Exclude relationships below the requested confidence. |
| `depth` | integer from 1 to 3 | Maximum traversal distance. |
| `max_nodes` | integer from 1 to 250 | Maximum number of returned entities. |

## Examples

Symbolic relationships only:

```http
GET /v1/entities/WND-ENT-000001/graph?types=symbolises,associated_with&depth=2
```

Approved high-confidence geographic relationships:

```http
GET /v1/entities/WND-ENT-000001/graph?types=located_in&statuses=approved&min_confidence=0.8
```

## Structural guarantee

Filtering is applied before traversal. A relationship that fails the filter cannot introduce its neighbouring entity into the result. This prevents orphaned nodes and misleading paths.

The response repeats the effective filter under `data.filters` and reports whether filtering was active under `meta.filtered`.
