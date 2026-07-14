ALTER TABLE wonder_email_reputation_settings
    ADD COLUMN soft_bounce_limit INTEGER NOT NULL DEFAULT 3 CHECK (soft_bounce_limit BETWEEN 1 AND 20),
    ADD COLUMN soft_bounce_window_days INTEGER NOT NULL DEFAULT 14 CHECK (soft_bounce_window_days BETWEEN 1 AND 90);

CREATE INDEX wonder_email_events_soft_bounce_idx
    ON wonder_email_events (lower(email), occurred_at)
    WHERE event_type='bounced' AND bounce_classification='soft';

CREATE OR REPLACE FUNCTION wonder_escalate_soft_bounces() RETURNS void LANGUAGE plpgsql AS $$
DECLARE settings RECORD;
BEGIN
    SELECT soft_bounce_limit,soft_bounce_window_days INTO settings
    FROM wonder_email_reputation_settings WHERE singleton=TRUE;

    INSERT INTO wonder_email_suppressions(uuid,email,reason,created_at)
    SELECT gen_random_uuid(),lower(email),'soft_bounce_escalation',NOW()
    FROM wonder_email_events
    WHERE event_type='bounced' AND bounce_classification='soft'
      AND occurred_at>=NOW()-(settings.soft_bounce_window_days||' days')::interval
    GROUP BY lower(email)
    HAVING COUNT(*)>=settings.soft_bounce_limit
    ON CONFLICT DO NOTHING;
END;
$$;
