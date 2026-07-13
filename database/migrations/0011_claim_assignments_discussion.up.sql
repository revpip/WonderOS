CREATE TABLE wonder_claim_assignments (
    uuid UUID PRIMARY KEY,
    claim_wonder_id TEXT NOT NULL REFERENCES wonder_claims(wonder_id) ON DELETE CASCADE,
    assignee_uuid UUID NOT NULL REFERENCES wonder_users(uuid) ON DELETE RESTRICT,
    assigned_by UUID NOT NULL REFERENCES wonder_users(uuid) ON DELETE RESTRICT,
    assignment_type TEXT NOT NULL CHECK (assignment_type IN ('research','review')),
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','completed','cancelled')),
    due_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ NULL
);

CREATE TABLE wonder_claim_comments (
    uuid UUID PRIMARY KEY,
    claim_wonder_id TEXT NOT NULL REFERENCES wonder_claims(wonder_id) ON DELETE CASCADE,
    author_uuid UUID NOT NULL REFERENCES wonder_users(uuid) ON DELETE RESTRICT,
    parent_uuid UUID NULL REFERENCES wonder_claim_comments(uuid) ON DELETE CASCADE,
    body TEXT NOT NULL CHECK (btrim(body) <> ''),
    mentions JSONB NOT NULL DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    edited_at TIMESTAMPTZ NULL
);

CREATE INDEX wonder_claim_assignments_claim_idx ON wonder_claim_assignments(claim_wonder_id,status,due_at);
CREATE INDEX wonder_claim_assignments_assignee_idx ON wonder_claim_assignments(assignee_uuid,status,due_at);
CREATE INDEX wonder_claim_comments_claim_idx ON wonder_claim_comments(claim_wonder_id,created_at);
