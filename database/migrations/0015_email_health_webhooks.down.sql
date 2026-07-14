DROP TABLE IF EXISTS wonder_email_suppressions;
DROP TABLE IF EXISTS wonder_email_events;
ALTER TABLE wonder_notification_deliveries
    DROP COLUMN IF EXISTS provider_status,
    DROP COLUMN IF EXISTS delivered_at,
    DROP COLUMN IF EXISTS bounced_at,
    DROP COLUMN IF EXISTS complained_at;