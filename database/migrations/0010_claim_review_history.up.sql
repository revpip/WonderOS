ALTER TABLE wonder_claims ADD COLUMN revision INTEGER NOT NULL DEFAULT 1 CHECK (revision > 0);
ALTER TABLE wonder_claims ADD COLUMN reviewed_by UUID NULL REFERENCES wonder_users(uuid) ON DELETE SET NULL;
ALTER TABLE wonder_claims ADD COLUMN reviewed_at TIMESTAMPTZ NULL;
ALTER TABLE wonder_claims ADD COLUMN updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

CREATE TABLE wonder_claim_revisions (
    claim_wonder_id TEXT NOT NULL REFERENCES wonder_claims(wonder_id) ON DELETE CASCADE,
    revision INTEGER NOT NULL CHECK (revision > 0),
    statement TEXT NOT NULL,
    claim_type TEXT NOT NULL,
    confidence NUMERIC(4,3) NOT NULL CHECK (confidence >= 0 AND confidence <= 1),
    status TEXT NOT NULL CHECK (status IN ('draft','review','approved','disputed','archived')),
    changed_by UUID NOT NULL REFERENCES wonder_users(uuid) ON DELETE RESTRICT,
    change_note TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (claim_wonder_id, revision)
);

INSERT INTO wonder_claim_revisions (claim_wonder_id,revision,statement,claim_type,confidence,status,changed_by,change_note,created_at)
SELECT c.wonder_id,1,c.statement,c.claim_type,c.confidence,c.status,u.uuid,'Initial claim snapshot',c.created_at
FROM wonder_claims c
CROSS JOIN LATERAL (SELECT uuid FROM wonder_users ORDER BY created_at LIMIT 1) u
ON CONFLICT DO NOTHING;

CREATE INDEX wonder_claim_revisions_claim_idx ON wonder_claim_revisions(claim_wonder_id, revision DESC);
CREATE INDEX wonder_claims_status_idx ON wonder_claims(status, updated_at DESC);