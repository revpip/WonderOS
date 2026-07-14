DROP FUNCTION IF EXISTS wonder_escalate_soft_bounces();
DROP INDEX IF EXISTS wonder_email_events_soft_bounce_idx;
ALTER TABLE wonder_email_reputation_settings
    DROP COLUMN IF EXISTS soft_bounce_window_days,
    DROP COLUMN IF EXISTS soft_bounce_limit;
