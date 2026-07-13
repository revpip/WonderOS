CREATE TABLE wonder_notifications (
    uuid UUID PRIMARY KEY,
    recipient_uuid UUID NOT NULL REFERENCES wonder_users(uuid) ON DELETE CASCADE,
    actor_uuid UUID NULL REFERENCES wonder_users(uuid) ON DELETE SET NULL,
    notification_type TEXT NOT NULL CHECK (notification_type IN ('assignment','mention','due_soon','overdue')),
    title TEXT NOT NULL CHECK (btrim(title) <> ''),
    body TEXT NOT NULL CHECK (btrim(body) <> ''),
    subject_type TEXT NOT NULL,
    subject_id TEXT NOT NULL,
    action_url TEXT NULL,
    deduplication_key TEXT NULL,
    read_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (recipient_uuid, deduplication_key)
);
CREATE INDEX wonder_notifications_recipient_idx ON wonder_notifications(recipient_uuid, read_at, created_at DESC);
CREATE INDEX wonder_notifications_subject_idx ON wonder_notifications(subject_type, subject_id);
