BEGIN;

CREATE TABLE entity_aliases (
    id BIGSERIAL PRIMARY KEY,
    entity_wonder_id VARCHAR(14) NOT NULL REFERENCES entities(wonder_id) ON DELETE CASCADE,
    alias VARCHAR(255) NOT NULL,
    normalised_alias VARCHAR(255) NOT NULL,
    alias_type VARCHAR(32) NOT NULL CHECK (alias_type IN ('common', 'scientific', 'historic', 'regional', 'spelling', 'other')),
    language VARCHAR(16) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (normalised_alias)
);

CREATE INDEX entity_aliases_entity_idx ON entity_aliases (entity_wonder_id);
CREATE INDEX entity_aliases_alias_idx ON entity_aliases (LOWER(alias));

COMMIT;
