# Inverse-aware graph retrieval

WonderOS stores one canonical directional relationship and presents it from the entity currently being viewed.

Stored edge:

```text
Barn Owl --roosts_in--> Church Towers
```

When `WND-ENT-000001` (Barn Owl) is the perspective:

```json
{
  "perspective_wonder_id": "WND-ENT-000001",
  "related_wonder_id": "WND-ENT-000002",
  "direction": "outgoing",
  "type": "roosts_in",
  "label": "roosts in",
  "canonical_type": "roosts_in"
}
```

When `WND-ENT-000002` (Church Towers) is the perspective:

```json
{
  "perspective_wonder_id": "WND-ENT-000002",
  "related_wonder_id": "WND-ENT-000001",
  "direction": "incoming",
  "type": "hosts_roost_of",
  "label": "hosts roost of",
  "canonical_type": "roosts_in"
}
```

The API retains `source_wonder_id`, `target_wonder_id` and `canonical_type` for auditability. The inverse presentation is derived from the governed relationship vocabulary and does not create a second database edge.

Symmetric types such as `associated_with` retain the same type and label from both perspectives.
