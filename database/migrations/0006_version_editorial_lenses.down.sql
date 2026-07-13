DROP TABLE IF EXISTS editorial_lens_revisions;

UPDATE editorial_lenses SET status = 'deprecated' WHERE status = 'draft';

ALTER TABLE editorial_lenses
    DROP CONSTRAINT editorial_lenses_status_check,
    DROP COLUMN revision,
    DROP COLUMN updated_by,
    ADD CONSTRAINT editorial_lenses_status_check CHECK (status IN ('active','deprecated'));
