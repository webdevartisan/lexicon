-- ----------------------------------------------------------------------------
-- Platform moderation: report categories, cases, and reports that outlive
-- the content they are about
-- ----------------------------------------------------------------------------
-- A report is a signal and a case is the decision, so they get separate
-- tables. content_reports replaces post_reports and comment_reports, and
-- deliberately has no foreign key to the reported row: those tables cascaded,
-- so deleting a post erased every complaint about it, and with it the record
-- of who filed reports that were later ruled unfounded.
--
-- The posts.reports_count and comments.reports_count mirrors stay, so the
-- blog dashboards keep their "Reported" badge without knowing about cases.
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS moderation_categories (
    slug VARCHAR(32) NOT NULL PRIMARY KEY COMMENT 'Stored on every report, so it never changes once used',
    label VARCHAR(60) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    auto_action ENUM('none','hide_content','suspend','escalate') NOT NULL DEFAULT 'none',
    threshold SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Counted reports that trigger auto_action; NULL when auto_action is none',
    suspension_hours INT UNSIGNED DEFAULT NULL COMMENT 'Length of an automatic suspension',
    execution ENUM('automatic','confirm') NOT NULL DEFAULT 'confirm' COMMENT 'confirm = the rule proposes and a person applies it',
    automation_acknowledged_by INT DEFAULT NULL COMMENT 'Administrator who accepted the risk of automating a high severity category',
    automation_acknowledged_at TIMESTAMP NULL DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Inactive categories stay on old reports but cannot be chosen',
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='What readers can report content for, and what the system does about it';

-- Only spam is automatic out of the box. Everything harsher proposes an action
-- for a person to confirm, and illegal content only ever escalates.
INSERT INTO moderation_categories
    (slug, label, description, severity, auto_action, threshold, suspension_hours, execution, sort_order)
VALUES
    ('spam', 'Spam', 'Advertising, scams, or repetitive content', 'low', 'hide_content', 3, NULL, 'automatic', 10),
    ('harassment', 'Harassment', 'Targets or intimidates a person', 'high', 'suspend', 3, 168, 'confirm', 20),
    ('hate', 'Hate speech', 'Attacks people for who they are', 'high', 'suspend', 3, 168, 'confirm', 30),
    ('misinformation', 'Misinformation', 'Presents false claims as fact', 'medium', 'hide_content', 5, NULL, 'confirm', 40),
    ('illegal', 'Illegal content', 'Breaks the law', 'critical', 'escalate', 1, NULL, 'confirm', 50),
    ('other', 'Something else', 'Tell us what is wrong', 'low', 'none', NULL, NULL, 'confirm', 60);

-- ----------------------------------------------------------------------------
-- Cases
-- ----------------------------------------------------------------------------
-- One live case per reported item. open_key holds "post:12" while the case is
-- unresolved and NULL after, and a UNIQUE key allows any number of NULLs, so
-- the database itself guarantees a second report joins the open case instead
-- of racing to open another. A report after a resolution starts a fresh case.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS moderation_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_type ENUM('post','comment') NOT NULL,
    subject_id INT NOT NULL,
    open_key VARCHAR(40) DEFAULT NULL COMMENT 'subject_type:subject_id while unresolved, NULL once resolved',
    subject_author_id INT DEFAULT NULL,
    subject_author_handle VARCHAR(50) DEFAULT NULL COMMENT 'Kept so the case still names the author after erasure',
    blog_id INT DEFAULT NULL,
    subject_snapshot TEXT DEFAULT NULL COMMENT 'Title or text as it read when the case opened',
    status ENUM('open','in_review','escalated','resolved') NOT NULL DEFAULT 'open' COMMENT 'The moderator work state',
    content_status ENUM('visible','hidden','removed') NOT NULL DEFAULT 'visible' COMMENT 'What readers see, independent of status',
    report_count INT NOT NULL DEFAULT 0,
    counted_report_count INT NOT NULL DEFAULT 0 COMMENT 'Reports that met the reporter standing rules',
    top_category VARCHAR(32) DEFAULT NULL COMMENT 'Most severe category among the reports',
    priority INT NOT NULL DEFAULT 0,
    pending_action VARCHAR(20) DEFAULT NULL COMMENT 'Action a rule proposed and a person has to confirm',
    pending_rule VARCHAR(32) DEFAULT NULL,
    pending_note VARCHAR(255) DEFAULT NULL COMMENT 'Why the rule did not act on its own',
    fired_rules JSON DEFAULT NULL COMMENT 'Categories whose rule already fired on this case',
    last_error VARCHAR(500) DEFAULT NULL COMMENT 'An automatic action that failed, shown in the queue until someone acts',
    first_reported_at TIMESTAMP NULL DEFAULT NULL,
    last_reported_at TIMESTAMP NULL DEFAULT NULL,
    resolution ENUM('dismissed','upheld') DEFAULT NULL,
    resolution_note VARCHAR(1000) DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_case_open (open_key),
    INDEX idx_case_subject (subject_type, subject_id),
    INDEX idx_case_queue (status, priority),
    INDEX idx_case_author (subject_author_id, resolution),
    INDEX idx_case_last_reported (last_reported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One moderation case per reported item; no foreign keys so it outlives the item and its author';

CREATE TABLE IF NOT EXISTS content_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    subject_type ENUM('post','comment') NOT NULL,
    subject_id INT NOT NULL,
    reporter_id INT DEFAULT NULL,
    reporter_handle VARCHAR(50) DEFAULT NULL,
    category VARCHAR(32) NOT NULL,
    details VARCHAR(1000) DEFAULT NULL,
    counts_toward_threshold TINYINT(1) NOT NULL DEFAULT 1,
    not_counted_reason VARCHAR(32) DEFAULT NULL COMMENT 'new_account, unfounded_history, filed_before_rules',
    outcome ENUM('pending','upheld','dismissed','unfounded') NOT NULL DEFAULT 'pending',
    outcome_by INT DEFAULT NULL,
    outcome_at TIMESTAMP NULL DEFAULT NULL,
    blog_reviewed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'The blog team approved the item; clears their badge, not the case',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_report_once (subject_type, subject_id, reporter_id),
    INDEX idx_report_case (case_id),
    INDEX idx_report_reporter (reporter_id, outcome, created_at),
    CONSTRAINT fk_report_case FOREIGN KEY (case_id) REFERENCES moderation_cases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per reader per reported item, kept after the item is gone';

-- ----------------------------------------------------------------------------
-- Carry the existing reports across
-- ----------------------------------------------------------------------------
-- They predate the reporter standing rules, so they are queued but do not
-- count toward an automatic action. Priority is recomputed by the application
-- on the next report or decision.
-- ----------------------------------------------------------------------------
INSERT INTO moderation_cases
    (subject_type, subject_id, open_key, subject_author_id, subject_author_handle, blog_id,
     subject_snapshot, first_reported_at, last_reported_at, created_at)
SELECT 'post', p.id, CONCAT('post:', p.id), p.author_id, ANY_VALUE(u.handle), p.blog_id,
       p.title, MIN(r.created_at), MAX(r.created_at), MIN(r.created_at)
  FROM post_reports r
  JOIN posts p ON p.id = r.post_id
  LEFT JOIN users u ON u.id = p.author_id
 GROUP BY p.id;

INSERT INTO moderation_cases
    (subject_type, subject_id, open_key, subject_author_id, subject_author_handle, blog_id,
     subject_snapshot, first_reported_at, last_reported_at, created_at)
SELECT 'comment', c.id, CONCAT('comment:', c.id), c.user_id, ANY_VALUE(u.handle), ANY_VALUE(p.blog_id),
       LEFT(c.content, 500), MIN(r.created_at), MAX(r.created_at), MIN(r.created_at)
  FROM comment_reports r
  JOIN comments c ON c.id = r.comment_id
  JOIN posts p ON p.id = c.post_id
  LEFT JOIN users u ON u.id = c.user_id
 GROUP BY c.id;

INSERT INTO content_reports
    (case_id, subject_type, subject_id, reporter_id, reporter_handle, category,
     counts_toward_threshold, not_counted_reason, created_at)
SELECT mc.id, 'post', r.post_id, r.user_id, u.handle, r.reason, 0, 'filed_before_rules', r.created_at
  FROM post_reports r
  JOIN moderation_cases mc ON mc.open_key = CONCAT('post:', r.post_id)
  LEFT JOIN users u ON u.id = r.user_id;

INSERT INTO content_reports
    (case_id, subject_type, subject_id, reporter_id, reporter_handle, category,
     counts_toward_threshold, not_counted_reason, created_at)
SELECT mc.id, 'comment', r.comment_id, r.user_id, u.handle, r.reason, 0, 'filed_before_rules', r.created_at
  FROM comment_reports r
  JOIN moderation_cases mc ON mc.open_key = CONCAT('comment:', r.comment_id)
  LEFT JOIN users u ON u.id = r.user_id;

UPDATE moderation_cases mc
   SET mc.report_count = (SELECT COUNT(*) FROM content_reports cr WHERE cr.case_id = mc.id);

DROP TABLE post_reports;
DROP TABLE comment_reports;

-- ----------------------------------------------------------------------------
-- Hiding a post
-- ----------------------------------------------------------------------------
-- Same move as blog suspension: every public query already filters on
-- status = 'published', so a new value hides the post from all of them, and
-- status_before_moderation is what makes restoring it exact.
-- ----------------------------------------------------------------------------
ALTER TABLE posts
    MODIFY COLUMN status ENUM('draft','pending','scheduled','published','archived','moderated') NOT NULL DEFAULT 'draft',
    ADD COLUMN status_before_moderation VARCHAR(20) DEFAULT NULL COMMENT 'Status to restore if a moderation hide is reversed',
    MODIFY COLUMN reports_count INT NOT NULL DEFAULT 0 COMMENT 'Open reports the blog team has not reviewed';

ALTER TABLE comments
    MODIFY COLUMN reports_count INT NOT NULL DEFAULT 0 COMMENT 'Open reports the blog team has not reviewed';

-- ----------------------------------------------------------------------------
-- System-triggered suspensions
-- ----------------------------------------------------------------------------
-- A suspension a rule applied has no person behind it, so suspended_by stays
-- NULL and source says why, with the rule and case that fired it.
-- ----------------------------------------------------------------------------
ALTER TABLE user_suspensions
    ADD COLUMN source ENUM('moderator','rule') NOT NULL DEFAULT 'moderator' AFTER suspended_by,
    ADD COLUMN rule VARCHAR(32) DEFAULT NULL COMMENT 'Category whose rule applied it' AFTER source,
    ADD COLUMN case_id INT DEFAULT NULL COMMENT 'Moderation case that led to it' AFTER rule;
