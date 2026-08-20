-- Comment threads: real nesting, pinning, removal tombstones, votes, reports.
--
-- parent_comment_id already existed but the app forced every reply up to the
-- thread root, so the column only ever held two levels. It now holds the true
-- parent and the thread is assembled as a real tree; indent depth is a render
-- concern, capped in the view rather than in the data.

ALTER TABLE comments
    ADD COLUMN upvotes INT NOT NULL DEFAULT 0 COMMENT 'Denormalised from comment_votes' AFTER content,
    ADD COLUMN downvotes INT NOT NULL DEFAULT 0 COMMENT 'Denormalised from comment_votes' AFTER upvotes,
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Tombstone marker; row survives so its replies keep their thread' AFTER status,
    ADD COLUMN deleted_by ENUM('author','moderator') DEFAULT NULL
        COMMENT 'Who removed it; drives the tombstone wording' AFTER deleted_at,
    ADD COLUMN pinned_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Set by the blog team; one pinned comment per post, sorts first' AFTER deleted_by,
    ADD COLUMN reports_count INT NOT NULL DEFAULT 0 COMMENT 'Denormalised from comment_reports' AFTER downvotes,
    ADD INDEX idx_comment_deleted (deleted_at),
    ADD INDEX idx_comment_pinned (post_id, pinned_at);

CREATE TABLE IF NOT EXISTS comment_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    value TINYINT NOT NULL COMMENT '1 = up, -1 = down',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_comment_vote (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_comment_vote_comment (comment_id),
    INDEX idx_comment_vote_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per user per voted comment';

CREATE TABLE IF NOT EXISTS comment_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    reason ENUM('spam','harassment','hate','misinformation','other') NOT NULL DEFAULT 'other',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_comment_report (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_comment_report_comment (comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per user per reported comment; reporting twice is a no-op';

-- ----------------------------------------------------------------------------
-- Post engagement, aligned with comments: a direction rather than a like-only
-- flag, plus reader reports.
--
-- post_likes is renamed rather than extended: a table called "likes" holding a
-- dislike is the kind of drift that costs an hour to untangle later.
-- ----------------------------------------------------------------------------

RENAME TABLE post_likes TO post_votes;

ALTER TABLE post_votes
    ADD COLUMN value TINYINT NOT NULL DEFAULT 1 COMMENT '1 = up, -1 = down' AFTER user_id;

ALTER TABLE posts
    ADD COLUMN reports_count INT NOT NULL DEFAULT 0 COMMENT 'Denormalised from post_reports';

CREATE TABLE IF NOT EXISTS post_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    reason ENUM('spam','harassment','hate','misinformation','other') NOT NULL DEFAULT 'other',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_post_report (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_post_report_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per user per reported post; reporting twice is a no-op';

-- ----------------------------------------------------------------------------
-- Who pinned, and where a clamped reply was actually aimed.
--
-- Nesting stops at CommentModel::MAX_DEPTH. Past it a reply becomes a sibling
-- of what it answered rather than a further indent, so parent_comment_id no
-- longer names the target. reply_to_comment_id is set only in that case; null
-- means "I answered my parent", which is the ordinary situation.
-- ----------------------------------------------------------------------------

ALTER TABLE comments
    ADD COLUMN pinned_by INT DEFAULT NULL COMMENT 'Who pinned it; drives the "Pinned by" byline' AFTER pinned_at,
    ADD COLUMN reply_to_comment_id INT DEFAULT NULL
        COMMENT 'Answer target when the depth cap made it differ from the parent' AFTER parent_comment_id,
    ADD CONSTRAINT fk_comment_pinned_by FOREIGN KEY (pinned_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD INDEX idx_comment_reply_to (reply_to_comment_id);

-- Lift replies that already sit deeper than the cap up to it, recording where
-- they were actually aimed so the @mention survives the move. One pass handles
-- a chain one level too deep, which is all the old two-level flattening could
-- produce; re-run it if a restore ever brings back something deeper.
UPDATE comments c
JOIN comments p ON p.id = c.parent_comment_id
JOIN comments gp ON gp.id = p.parent_comment_id
JOIN comments ggp ON ggp.id = gp.parent_comment_id
SET c.reply_to_comment_id = COALESCE(c.reply_to_comment_id, c.parent_comment_id),
    c.parent_comment_id = gp.id
WHERE ggp.parent_comment_id IS NOT NULL;
