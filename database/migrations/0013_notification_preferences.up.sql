CREATE TABLE wonder_notification_preferences (
    user_uuid UUID PRIMARY KEY REFERENCES wonder_users(uuid) ON DELETE CASCADE,
    assignment_alerts BOOLEAN NOT NULL DEFAULT TRUE,
    mention_alerts BOOLEAN NOT NULL DEFAULT TRUE,
    due_alerts BOOLEAN NOT NULL DEFAULT TRUE,
    email_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    daily_digest BOOLEAN NOT NULL DEFAULT FALSE,
    quiet_hours_start TIME NULL,
    quiet_hours_end TIME NULL,
    timezone TEXT NOT NULL DEFAULT 'Europe/London',
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK ((quiet_hours_start IS NULL AND quiet_hours_end IS NULL) OR (quiet_hours_start IS NOT NULL AND quiet_hours_end IS NOT NULL))
);

CREATE TABLE wonder_notification_deliveries (
    uuid UUID PRIMARY KEY,
    notification_uuid UUID NOT NULL REFERENCES wonder_notifications(uuid) ON DELETE CASCADE,
    channel TEXT NOT NULL CHECK (channel IN ('in_app','email','digest')),
    status TEXT NOT NULL DEFAULT 'queued' CHECK (status IN ('queued','sent','suppressed','failed')),
    available_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    attempted_at TIMESTAMPTZ NULL,
    sent_at TIMESTAMPTZ NULL,
    failure_reason TEXT NULL,
    UNIQUE(notification_uuid, channel)
);

CREATE INDEX wonder_notification_deliveries_queue_idx ON wonder_notification_deliveries(status,available_at);
CREATE INDEX wonder_notification_preferences_digest_idx ON wonder_notification_preferences(daily_digest,email_enabled);