ALTER TABLE editorial_lenses
    DROP CONSTRAINT editorial_lenses_status_check;

ALTER TABLE editorial_lenses
    ADD COLUMN revision INTEGER NOT NULL DEFAULT 1 CHECK (revision >= 1),
    ADD COLUMN updated_by TEXT NOT NULL DEFAULT 'system' CHECK (btrim(updated_by) <> ''),
    ADD CONSTRAINT editorial_lenses_status_check CHECK (status IN ('draft','active','deprecated'));

CREATE TABLE editorial_lens_revisions (
    lens_slug TEXT NOT NULL REFERENCES editorial_lenses(slug) ON DELETE CASCADE,
    revision INTEGER NOT NULL CHECK (revision >= 1),
    snapshot JSONB NOT NULL,
    changed_by TEXT NOT NULL CHECK (btrim(changed_by) <> ''),
    changed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (lens_slug, revision)
);

INSERT INTO editorial_lens_revisions (lens_slug, revision, snapshot, changed_by)
SELECT slug, revision,
       jsonb_build_object(
           'slug', slug,
           'name', name,
           'description', description,
           'relationship_types', relationship_types,
           'relationship_statuses', relationship_statuses,
           'minimum_confidence', minimum_confidence,
           'status', status,
           'revision', revision
       ),
       updated_by
FROM editorial_lenses;

CREATE INDEX editorial_lens_revisions_changed_at_idx
    ON editorial_lens_revisions (lens_slug, changed_at DESC);
