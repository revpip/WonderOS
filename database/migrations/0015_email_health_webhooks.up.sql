ALTER TABLE wonder_notification_deliveries
    ADD COLUMN provider_status TEXT NULL,
    ADD COLUMN delivered_at TIMESTAMPTZ NULL,
    ADD COLUMN bounced_at TIMESTAMPTZ NULL,
    ADD COLUMN complained_at TIMESTAMPTZ NULL;

CREATE TABLE wonder_email_events (
    provider_event_id TEXT PRIMARY KEY,
    provider_message_id TEXT NULL,
    event_type TEXT NOT NULL CHECK (event_type IN ('delivered','deferred','bounced','blocked','complained','opened','clicked')),
    recipient_email TEXT NOT NULL,
    payload JSONB NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    received_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE wonder_email_suppressions (
    email CITEXT PRIMARY KEY,
    reason TEXT NOT NULL CHECK (reason IN ('hard_bounce','complaint','blocked','manual')),
    provider_event_id TEXT NULL REFERENCES wonder_email_events(provider_event_id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    released_at TIMESTAMPTZ NULL
);

CREATE INDEX wonder_email_events_message_idx ON wonder_email_events(provider_message_id,occurred_at DESC);
CREATE INDEX wonder_email_events_type_idx ON wonder_email_events(event_type,occurred_at DESC);
CREATE INDEX wonder_email_suppressions_active_idx ON wonder_email_suppressions(email) WHERE released_at IS NULL;