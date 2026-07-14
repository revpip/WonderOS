ALTER TABLE wonder_email_events ADD COLUMN IF NOT EXISTS bounce_class TEXT NULL CHECK (bounce_class IN ('soft','hard'));
ALTER TABLE wonder_email_suppressions ADD COLUMN IF NOT EXISTS released_at TIMESTAMPTZ NULL;
ALTER TABLE wonder_email_suppressions ADD COLUMN IF NOT EXISTS released_by UUID NULL REFERENCES wonder_users(uuid) ON DELETE RESTRICT;
ALTER TABLE wonder_email_suppressions ADD COLUMN IF NOT EXISTS release_reason TEXT NULL;

CREATE TABLE wonder_email_reputation_settings (
    singleton BOOLEAN PRIMARY KEY DEFAULT TRUE CHECK (singleton),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','paused')),
    bounce_rate_threshold NUMERIC(6,5) NOT NULL DEFAULT 0.05000 CHECK (bounce_rate_threshold > 0 AND bounce_rate_threshold <= 1),
    complaint_rate_threshold NUMERIC(6,5) NOT NULL DEFAULT 0.00100 CHECK (complaint_rate_threshold > 0 AND complaint_rate_threshold <= 1),
    minimum_sample_size INTEGER NOT NULL DEFAULT 100 CHECK (minimum_sample_size >= 1),
    paused_at TIMESTAMPTZ NULL,
    pause_reason TEXT NULL,
    updated_by UUID NULL REFERENCES wonder_users(uuid) ON DELETE RESTRICT,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
INSERT INTO wonder_email_reputation_settings(singleton) VALUES(TRUE) ON CONFLICT DO NOTHING;

CREATE INDEX wonder_email_suppressions_active_idx ON wonder_email_suppressions(lower(email)) WHERE released_at IS NULL;
CREATE INDEX wonder_email_events_reputation_idx ON wonder_email_events(event_type, occurred_at);
