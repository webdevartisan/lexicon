-- ============================================================
-- Blog RBAC Foundation Migration — 2026-06-22
-- Apply to production/dev DB BEFORE deploying application code.
-- ============================================================

START TRANSACTION;

-- 1. Backfill removed status values to 'draft' before altering the ENUM.
UPDATE posts SET status = 'draft'
WHERE status IN ('pending', 'pending_review', 'approved', 'rejected');

-- 2. Backfill removed workflow_state values before altering the ENUM.
--    'idea' and 'ready_to_publish' were never written by application code,
--    but we normalise defensively in case any row carries them.
UPDATE posts SET workflow_state = 'draft'
WHERE workflow_state IN ('idea', 'ready_to_publish');

-- 3. Trim posts.status ENUM to public-lifecycle values only.
ALTER TABLE posts
    MODIFY COLUMN status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft';

-- 4. Trim posts.workflow_state ENUM — remove dead 'idea' and 'ready_to_publish'.
ALTER TABLE posts
    MODIFY COLUMN workflow_state ENUM('draft','in_review','needs_changes','approved') NOT NULL DEFAULT 'draft';

-- 5. Add workflow toggle to blog_settings.
ALTER TABLE blog_settings
    ADD COLUMN workflow_enabled BOOLEAN NOT NULL DEFAULT FALSE
        COMMENT 'When true, posts require review/approve before publishing';

-- 6. Create blog_invitations table.
CREATE TABLE IF NOT EXISTS blog_invitations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    blog_id     INT NOT NULL,
    email       VARCHAR(255) NOT NULL,
    role        VARCHAR(32) NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE COMMENT 'sha256 hash of raw token',
    invited_by  INT NOT NULL,
    expires_at  TIMESTAMP NOT NULL,
    accepted_at TIMESTAMP NULL DEFAULT NULL,
    declined_at TIMESTAMP NULL DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_email_blog (email, blog_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pending blog collaboration invitations';

-- 7. Create notifications table.
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    type       VARCHAR(64) NOT NULL,
    data       JSON NOT NULL,
    read_at    TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, read_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='In-app notifications for workflow and collaboration events';

COMMIT;
