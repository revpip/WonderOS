CREATE TABLE entity_relationships (
    uuid UUID PRIMARY KEY,
    source_wonder_id VARCHAR(14) NOT NULL REFERENCES entities(wonder_id) ON DELETE CASCADE,
    target_wonder_id VARCHAR(14) NOT NULL REFERENCES entities(wonder_id) ON DELETE CASCADE,
    relationship_type VARCHAR(80) NOT NULL,
    context TEXT NULL,
    confidence NUMERIC(4,3) NOT NULL DEFAULT 0.500 CHECK (confidence >= 0 AND confidence <= 1),
    status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','review','approved','archived')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT relationship_no_self_reference CHECK (source_wonder_id <> target_wonder_id),
    CONSTRAINT relationship_unique_edge UNIQUE (source_wonder_id, target_wonder_id, relationship_type)
);

CREATE INDEX entity_relationships_source_idx ON entity_relationships (source_wonder_id);
CREATE INDEX entity_relationships_target_idx ON entity_relationships (target_wonder_id);
CREATE INDEX entity_relationships_type_idx ON entity_relationships (relationship_type);
