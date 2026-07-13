DROP INDEX IF EXISTS wonder_claims_status_idx;
DROP TABLE IF EXISTS wonder_claim_revisions;
ALTER TABLE wonder_claims DROP COLUMN IF EXISTS updated_at;
ALTER TABLE wonder_claims DROP COLUMN IF EXISTS reviewed_at;
ALTER TABLE wonder_claims DROP COLUMN IF EXISTS reviewed_by;
ALTER TABLE wonder_claims DROP COLUMN IF EXISTS revision;