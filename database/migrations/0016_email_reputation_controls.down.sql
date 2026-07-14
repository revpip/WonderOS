DROP INDEX IF EXISTS wonder_email_events_reputation_idx;
DROP INDEX IF EXISTS wonder_email_suppressions_active_idx;
DROP TABLE IF EXISTS wonder_email_reputation_settings;
ALTER TABLE wonder_email_suppressions DROP COLUMN IF EXISTS release_reason;
ALTER TABLE wonder_email_suppressions DROP COLUMN IF EXISTS released_by;
ALTER TABLE wonder_email_suppressions DROP COLUMN IF EXISTS released_at;
ALTER TABLE wonder_email_events DROP COLUMN IF EXISTS bounce_class;
