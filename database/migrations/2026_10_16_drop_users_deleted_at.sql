-- ----------------------------------------------------------------------------
-- Drop users.deleted_at. Nothing ever soft-deleted an account: erasure removes
-- the row through AccountErasureService, so the column was always NULL and the
-- IS NULL filters on it matched every user. comments.deleted_at is a different
-- column (the tombstone that keeps reply threads intact) and stays.

ALTER TABLE users
    DROP INDEX idx_deleted_at,
    DROP COLUMN deleted_at;
