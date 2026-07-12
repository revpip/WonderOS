BEGIN;

CREATE TABLE wonder_id_sequences (
    prefix CHAR(3) PRIMARY KEY,
    last_value INTEGER NOT NULL CHECK (last_value >= 0 AND last_value < 999999)
);

INSERT INTO wonder_id_sequences (prefix, last_value)
VALUES ('ENT', 0)
ON CONFLICT (prefix) DO NOTHING;

CREATE TABLE entities (
    uuid UUID PRIMARY KEY,
    wonder_id VARCHAR(14) NOT NULL UNIQUE,
    canonical_name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    family VARCHAR(120) NOT NULL,
    entity_type VARCHAR(120) NOT NULL,
    status VARCHAR(32) NOT NULL CHECK (status IN ('draft', 'review', 'approved', 'archived')),
    confidence NUMERIC(4,3) NOT NULL CHECK (confidence >= 0 AND confidence <= 1),
    revision INTEGER NOT NULL CHECK (revision >= 1),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX entities_canonical_name_idx ON entities (LOWER(canonical_name));
CREATE INDEX entities_family_type_idx ON entities (family, entity_type);

COMMIT;
