DROP INDEX IF EXISTS wonder_notification_deliveries_retry_idx;
DROP TABLE IF EXISTS wonder_notification_delivery_attempts;
ALTER TABLE wonder_notification_deliveries
    DROP COLUMN IF EXISTS last_response_code,
    DROP COLUMN IF EXISTS provider_message_id,
    DROP COLUMN IF EXISTS claimed_at,
    DROP COLUMN IF EXISTS next_attempt_at,
    DROP COLUMN IF EXISTS attempts;