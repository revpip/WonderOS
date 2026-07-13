CREATE TABLE wonder_sources (
    wonder_id TEXT PRIMARY KEY CHECK (wonder_id ~ '^WND-SRC-[0-9]{6}$'),
    title TEXT NOT NULL CHECK (btrim(title) <> ''),
    url TEXT NULL,
    publisher TEXT NULL,
    published_at DATE NULL,
    source_type TEXT NOT NULL CHECK (source_type IN ('book','journal','website','archive','interview','dataset','other')),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','archived')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE wonder_claims (
    wonder_id TEXT PRIMARY KEY CHECK (wonder_id ~ '^WND-CLM-[0-9]{6}$'),
    entity_wonder_id TEXT NOT NULL REFERENCES entities(wonder_id) ON DELETE CASCADE,
    statement TEXT NOT NULL CHECK (btrim(statement) <> ''),
    claim_type TEXT NOT NULL DEFAULT 'fact' CHECK (claim_type IN ('fact','interpretation','tradition','quotation','measurement')),
    confidence NUMERIC(4,3) NOT NULL CHECK (confidence >= 0 AND confidence <= 1),
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','review','approved','disputed','archived')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE wonder_evidence (
    uuid UUID PRIMARY KEY,
    claim_wonder_id TEXT NOT NULL REFERENCES wonder_claims(wonder_id) ON DELETE CASCADE,
    source_wonder_id TEXT NOT NULL REFERENCES wonder_sources(wonder_id) ON DELETE RESTRICT,
    locator TEXT NULL,
    excerpt TEXT NULL,
    stance TEXT NOT NULL DEFAULT 'supports' CHECK (stance IN ('supports','contradicts','contextualises')),
    strength NUMERIC(4,3) NOT NULL CHECK (strength >= 0 AND strength <= 1),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (claim_wonder_id, source_wonder_id, locator, stance)
);

CREATE TABLE wonder_knowledge_sequences (
    prefix CHAR(3) PRIMARY KEY CHECK (prefix IN ('CLM','SRC')),
    last_value INTEGER NOT NULL DEFAULT 0 CHECK (last_value >= 0 AND last_value < 999999)
);
INSERT INTO wonder_knowledge_sequences(prefix,last_value) VALUES ('CLM',0),('SRC',0);

CREATE INDEX wonder_claims_entity_idx ON wonder_claims(entity_wonder_id,status);
CREATE INDEX wonder_evidence_claim_idx ON wonder_evidence(claim_wonder_id);
CREATE INDEX wonder_evidence_source_idx ON wonder_evidence(source_wonder_id);