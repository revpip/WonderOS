CREATE TABLE wonder_account_activity (
    id BIGSERIAL PRIMARY KEY,
    actor_uuid UUID NOT NULL REFERENCES wonder_users(uuid) ON DELETE RESTRICT,
    subject_uuid UUID NULL REFERENCES wonder_users(uuid) ON DELETE SET NULL,
    action TEXT NOT NULL CHECK (btrim(action) <> ''),
    context JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX wonder_account_activity_created_idx ON wonder_account_activity(created_at DESC);
CREATE INDEX wonder_account_activity_subject_idx ON wonder_account_activity(subject_uuid,created_at DESC);