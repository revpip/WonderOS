# Graph Traversal

WonderOS exposes bounded graph exploration through:

```text
GET /v1/entities/{wonderId}/graph?depth=2&max_nodes=100
```

## Safety boundaries

- `depth` must be between 1 and 3.
- `max_nodes` must be between 1 and 250.
- Each canonical entity is visited once.
- Each stored relationship is returned once.
- Cycles do not recurse indefinitely.
- Truncated responses never contain edges to omitted nodes.

## Example

```text
Barn Owl
  → roosts in → Church Towers
  → part of → Historic Churches
  → associated with → Medieval Architecture
```

Each node includes a `distance` from the root. Each edge is presented from the traversal perspective while retaining its canonical stored source, target and relationship type for auditability.

## Response shape

```json
{
  "success": true,
  "data": {
    "root_wonder_id": "WND-ENT-000001",
    "depth": 2,
    "nodes": [],
    "edges": [],
    "truncated": false
  },
  "meta": {
    "node_count": 4,
    "edge_count": 3
  },
  "links": {}
}
```

Genesis traversal is breadth-first and intentionally small. Scoring, filtering, path finding, pagination, graph snapshots and visual layout remain later capabilities.
