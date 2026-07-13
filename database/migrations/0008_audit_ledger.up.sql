CREATE TABLE wonder_audit_events (
    id BIGSERIAL PRIMARY KEY,
    event_uuid UUID NOT NULL UNIQUE,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    actor_uuid UUID NULL REFERENCES wonder_users(uuid) ON DELETE SET NULL,
    actor_email TEXT NOT NULL,
    action TEXT NOT NULL CHECK (action ~ '^[a-z]+(?:\.[a-z_]+)+$'),
    subject_type TEXT NOT NULL CHECK (subject_type ~ '^[a-z_]+$'),
    subject_id TEXT NOT NULL,
    summary TEXT NOT NULL CHECK (btrim(summary) <> ''),
    metadata JSONB NOT NULL DEFAULT '{}'::JSONB,
    request_id UUID NULL,
    ip_address INET NULL
);

CREATE INDEX wonder_audit_occurred_idx ON wonder_audit_events (occurred_at DESC);
CREATE INDEX wonder_audit_actor_idx ON wonder_audit_events (actor_uuid, occurred_at DESC);
CREATE INDEX wonder_audit_subject_idx ON wonder_audit_events (subject_type, subject_id, occurred_at DESC);
CREATE INDEX wonder_audit_action_idx ON wonder_audit_events (action, occurred_at DESC);

CREATE OR REPLACE FUNCTION wonder_audit_immutable() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'WonderOS audit events are immutable';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER wonder_audit_no_update BEFORE UPDATE ON wonder_audit_events FOR EACH ROW EXECUTE FUNCTION wonder_audit_immutable();
CREATE TRIGGER wonder_audit_no_delete BEFORE DELETE ON wonder_audit_events FOR EACH ROW EXECUTE FUNCTION wonder_audit_immutable();