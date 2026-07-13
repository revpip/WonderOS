# Editorial lenses

List active lenses:

```http
GET /v1/editorial-lenses
```

Apply a named lens:

```http
GET /v1/entities/WND-ENT-000001/graph?lens=symbolism&depth=2
```

Founding lenses:

- `symbolism`
- `geography`
- `historic-influence`
- `approved-knowledge`
- `high-confidence`
- `mythology`
- `travel`

A lens supplies the baseline `types`, `statuses`, and `minimum_confidence`. Explicit query parameters override the corresponding lens field, allowing a deliberate refinement without modifying the stored preset.

```http
GET /v1/entities/WND-ENT-000001/graph?lens=symbolism&min_confidence=0.9
```

Responses include the resolved lens definition and its slug in metadata. Unknown or deprecated lenses are rejected rather than silently falling back to an unfiltered graph.
