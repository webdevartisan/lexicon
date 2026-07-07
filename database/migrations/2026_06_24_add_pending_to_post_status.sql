-- Add 'pending' to posts.status ENUM so authors can use status=pending
-- as the canonical "needs review" signal (replaces the old standalone
-- "Submit for Review" button — saving with status=pending now auto-
-- triggers the workflow pipeline).

ALTER TABLE posts
    MODIFY COLUMN status ENUM('draft','pending','published','archived')
    NOT NULL DEFAULT 'draft';
