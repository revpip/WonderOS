ALTER TABLE wonder_notification_deliveries
    ADD COLUMN attempts INTEGER NOT NULL DEFAULT 0 CHECK (attempts >= 0),
    ADD COLUMN next_attempt_at TIMESTAMPTZ NULL,
    ADD COLUMN claimed_at TIMESTAMPTZ NULL,
    ADD COLUMN provider_message_id TEXT NULL,
    ADD COLUMN last_response_code INTEGER NULL;

CREATE TABLE wonder_notification_delivery_attempts (
    uuid UUID PRIMARY KEY,
    delivery_uuid UUID NOT NULL REFERENCES wonder_notification_deliveries(uuid) ON DELETE CASCADE,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    succeeded BOOLEAN NOT NULL,
    response_code INTEGER NULL,
    provider_message_id TEXT NULL,
    failure_reason TEXT NULL
);

CREATE INDEX wonder_notification_delivery_attempts_delivery_idx
    ON wonder_notification_delivery_attempts(delivery_uuid, attempted_at DESC);

CREATE INDEX wonder_notification_deliveries_retry_idx
    ON wonder_notification_deliveries(status, COALESCE(next_attempt_at, available_at));