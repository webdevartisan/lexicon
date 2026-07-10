-- ============================================================================
-- Multi-Role Blog Platform Database Schema - Unified Migration v1.1
-- ============================================================================
-- 
-- Purpose: Complete database schema for a multi-role blog platform with
--          granular permissions, blog ownership, and content workflow management.
--
-- Installation: Execute this file on a fresh database. Do not run on existing
--               production databases without proper backup and testing.
--
-- Dependencies: MySQL 5.7+ or MariaDB 10.2+ with InnoDB support
--
-- Security: Ensures all tables use InnoDB engine with proper foreign key 
--           constraints and utf8mb4 character set for full Unicode support.
--
-- Version: 1.1.0
-- Last Updated: February 2026
-- Changes: Added migrations table, timestamp columns, and additional reserved slugs
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- ============================================================================
-- CORE TABLES (No Foreign Key Dependencies)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Users Table
-- ----------------------------------------------------------------------------
-- We store core user authentication and profile data separately from extended
-- profile information to optimize query performance on authentication paths.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL COMMENT 'bcrypt or password_hash() hashed',
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    display_name_cached VARCHAR(150) DEFAULT NULL COMMENT 'Cached computed display name',
    is_active BOOLEAN DEFAULT TRUE,
    posts_count INT NOT NULL DEFAULT 0 COMMENT 'Denormalized count for performance',
    comments_received_count INT NOT NULL DEFAULT 0 COMMENT 'Denormalized count for performance',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL COMMENT 'Soft delete timestamp',
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_is_active (is_active),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Core user authentication and identity data';

-- ----------------------------------------------------------------------------
-- Roles Table
-- ----------------------------------------------------------------------------
-- We define hierarchical roles with levels to enable role comparison and
-- inheritance patterns in the authorization layer.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    role_slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    scope ENUM('system', 'blog') NOT NULL DEFAULT 'blog' COMMENT 'system = control panel role, blog = per-blog collaboration role',
    is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Shipped role referenced by code; cannot be deleted',
    level INT NOT NULL DEFAULT 0 COMMENT 'Hierarchical level (higher = more authority)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (role_slug),
    INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Role definitions with hierarchical levels';

-- ----------------------------------------------------------------------------
-- Permissions Table
-- ----------------------------------------------------------------------------
-- We use resource-action pairs to enable fine-grained authorization checks
-- following a capability-based security model.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    permission_slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    resource VARCHAR(50) DEFAULT NULL COMMENT 'Target resource type (posts, users, blogs, etc)',
    action VARCHAR(50) DEFAULT NULL COMMENT 'Action verb (create, read, update, delete, etc)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (permission_slug),
    INDEX idx_resource_action (resource, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Permission definitions using resource-action pattern';

-- ----------------------------------------------------------------------------
-- Categories Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_blog_slug (blog_id, slug),
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Post categories for content organization (scoped per blog)';

-- ----------------------------------------------------------------------------
-- Tags Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tags_blog_slug (blog_id, slug),
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Post tags for flexible content labeling (scoped per blog)';

-- ----------------------------------------------------------------------------
-- Settings Table
-- ----------------------------------------------------------------------------
-- We store application-wide configuration as key-value pairs for flexibility
-- without requiring schema changes for new settings.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    value TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Application-wide configuration settings';

-- ----------------------------------------------------------------------------
-- Reserved Slugs Table
-- ----------------------------------------------------------------------------
-- We maintain a registry of reserved slugs to prevent users from claiming
-- slugs that conflict with routing or system functionality.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reserved_slugs (
    slug VARCHAR(100) PRIMARY KEY,
    reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registry of reserved slugs for routing protection';

-- ----------------------------------------------------------------------------
-- Migrations Table
-- ----------------------------------------------------------------------------
-- We track which migration files have been applied to enable version control
-- and prevent duplicate execution of schema changes.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Migration history for schema version control';

-- ============================================================================
-- USER-DEPENDENT TABLES (Foreign Keys to users)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- User Profiles Table
-- ----------------------------------------------------------------------------
-- We separate extended profile data from core user authentication to optimize
-- authentication queries and allow for richer profile features.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT PRIMARY KEY,
    slug VARCHAR(100) DEFAULT NULL COMMENT 'URL-friendly username for public profiles',
    bio TEXT DEFAULT NULL,
    avatar_url VARCHAR(512) DEFAULT NULL,
    location VARCHAR(120) DEFAULT NULL,
    occupation VARCHAR(120) DEFAULT NULL,
    is_public BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether profile is publicly viewable',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_profiles_slug (slug),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Extended user profile information and public display settings';

-- ----------------------------------------------------------------------------
-- User Social Links Table
-- ----------------------------------------------------------------------------
-- We store social media links separately to allow multiple links per user
-- without cluttering the profile table with nullable columns.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_social_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    network VARCHAR(50) NOT NULL COMMENT 'Social network identifier (twitter, linkedin, github, etc)',
    url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_network (user_id, network),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User social media links and external profiles';

-- ----------------------------------------------------------------------------
-- User Preferences Table
-- ----------------------------------------------------------------------------
-- We centralize user preferences to enable consistent UX personalization
-- across the platform without scattering settings throughout the schema.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT PRIMARY KEY,
    default_blog_id INT DEFAULT NULL COMMENT 'Users default blog for quick post creation',
    display_name_preference ENUM('name','username') NOT NULL DEFAULT 'username',
    default_post_visibility ENUM('public','private','unlisted') NOT NULL DEFAULT 'public',
    timezone VARCHAR(64) DEFAULT NULL COMMENT 'IANA timezone identifier (e.g., Europe/Athens)',
    notify_comments BOOLEAN NOT NULL DEFAULT TRUE,
    notify_likes BOOLEAN NOT NULL DEFAULT TRUE,
    notify_post_status BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Email me on post review/approve/publish events',
    notify_role_changes BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Email me when my collaborator role changes or I am removed',
    notify_invites BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Email me when invited to a blog or when an invite I sent is declined',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_default_blog (default_blog_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User-specific preference and notification settings';

-- ----------------------------------------------------------------------------
-- Blogs Table
-- ----------------------------------------------------------------------------
-- We track blog lifecycle with tri-state status (draft, published, archived)
-- instead of boolean flags for clearer state management and querying.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_name VARCHAR(200) NOT NULL,
    blog_slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    owner_id INT NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Admin pick for the explore page featured blogs',
    published_at TIMESTAMP NULL COMMENT 'When blog was first published',
    archived_at TIMESTAMP NULL COMMENT 'When blog was archived',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_owner (owner_id),
    INDEX idx_slug (blog_slug),
    INDEX idx_status (status),
    INDEX idx_status_published (status, published_at),
    INDEX idx_featured (is_featured, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Blog instances with ownership and lifecycle tracking';

-- ----------------------------------------------------------------------------
-- Blog Settings Table
-- ----------------------------------------------------------------------------
-- We separate blog configuration from core blog data to allow for extensible
-- theming and customization without altering the main blogs table.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blog_settings (
    blog_id INT PRIMARY KEY,
    theme VARCHAR(64) DEFAULT NULL,
    default_locale VARCHAR(5) NOT NULL DEFAULT 'en',
    timezone VARCHAR(64) DEFAULT NULL COMMENT 'IANA timezone for scheduling posts',
    meta_title VARCHAR(70) DEFAULT NULL COMMENT 'SEO meta title override',
    meta_description VARCHAR(160) DEFAULT NULL COMMENT 'SEO meta description',
    indexable BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Allow search engine indexing',
    comments_enabled BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Blog-wide comment setting',
    replies_auto_publish BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'When on, replies publish instantly; owner moderates retroactively',
    banner_path VARCHAR(255) DEFAULT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    favicon_path VARCHAR(255) DEFAULT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Whether this is the users primary blog',
    workflow_enabled BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'When true, posts require review/approve before publishing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    INDEX idx_is_primary (is_primary),
    INDEX idx_indexable (indexable)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Blog-specific configuration and display settings';

-- ----------------------------------------------------------------------------
-- Blog Users Table (formerly blog_authors)
-- ----------------------------------------------------------------------------
-- We track blog membership with roles to enable flexible permission models
-- where users can have different capabilities within different blogs.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blog_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'author' COMMENT 'Blog-specific role (author, editor, etc)',
    assigned_by INT NOT NULL COMMENT 'User ID who granted this blog membership',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_blog_user (blog_id, user_id),
    INDEX idx_blog (blog_id),
    INDEX idx_user (user_id),
    INDEX idx_blog_user_role (blog_id, user_id, role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Blog membership and role assignments for multi-user blogs';

-- ----------------------------------------------------------------------------
-- Blog Invitations Table
-- ----------------------------------------------------------------------------
-- Pending collaboration invites. Tokens are stored as sha256 hashes; the raw
-- token travels only in the email link. One pending invite per email+blog.
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Notifications Table
-- ----------------------------------------------------------------------------
-- In-app notifications for workflow and collaboration events. The `data`
-- column holds a JSON payload keyed by `type`.
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Posts Table
-- ----------------------------------------------------------------------------
-- `status` is the public lifecycle (draft/published/archived); `workflow_state`
-- is the editorial pipeline (draft/in_review/needs_changes/approved). Publishing
-- is a status concern and never mutates workflow_state.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    author_id INT NOT NULL,
    category_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content LONGTEXT DEFAULT NULL,
    excerpt TEXT DEFAULT NULL,
    featured_image VARCHAR(255) DEFAULT NULL,
    status ENUM('draft','pending','published','archived') NOT NULL DEFAULT 'draft',
    workflow_state ENUM('draft','in_review','needs_changes','approved') NOT NULL DEFAULT 'draft',
    visibility ENUM('public','private','unlisted') NOT NULL DEFAULT 'public',
    comments_enabled BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Post-level comment override',
    is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Headlines the blog landing; one per blog',
    featured_on_home TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Admin pick shown on the site front page',
    published_at TIMESTAMP NULL,
    timezone VARCHAR(50) DEFAULT 'UTC' COMMENT 'Timezone for scheduled publishing',
    last_workflow_by INT DEFAULT NULL COMMENT 'Last user to change workflow state',
    last_workflow_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (last_workflow_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_blog_slug (blog_id, slug),
    INDEX idx_status (status),
    INDEX idx_workflow_state (workflow_state),
    INDEX idx_visibility (visibility),
    INDEX idx_blog (blog_id),
    INDEX idx_author (author_id),
    INDEX idx_category (category_id),
    INDEX idx_published (published_at),
    INDEX idx_status_published (status, published_at),
    INDEX idx_author_created (author_id, created_at),
    INDEX idx_blog_published (blog_id, published_at),
    INDEX idx_blog_featured (blog_id, is_featured),
    INDEX idx_featured_home (featured_on_home, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Blog posts with workflow state and visibility controls';

-- ----------------------------------------------------------------------------
-- Post Tags Junction Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Many-to-many relationship between posts and tags';

-- ----------------------------------------------------------------------------
-- Post Reviewers Table
-- ----------------------------------------------------------------------------
-- We track reviewer assignments separately to enable multiple reviewers per
-- post and maintain an audit trail of the review process.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS post_reviewers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    review_status ENUM('pending','in_progress','completed') DEFAULT 'pending',
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_post_reviewer (post_id, reviewer_id),
    INDEX idx_post (post_id),
    INDEX idx_reviewer (reviewer_id),
    INDEX idx_review_status (review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Reviewer assignments for post review workflow';

-- ----------------------------------------------------------------------------
-- Reviews Table
-- ----------------------------------------------------------------------------
-- We store reviewer feedback separately from assignments to maintain history
-- and allow for multiple review rounds if needed.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    feedback TEXT DEFAULT NULL,
    decision ENUM('pending','approved','rejected','needs_revision') DEFAULT 'pending',
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_post (post_id),
    INDEX idx_reviewer (reviewer_id),
    INDEX idx_decision (decision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Review feedback and decisions for posts under review';

-- ----------------------------------------------------------------------------
-- Comments Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT DEFAULT NULL COMMENT 'NULL allows for anonymous comments if enabled',
    parent_comment_id INT DEFAULT NULL COMMENT 'Threaded replies; NULL = top-level',
    content TEXT NOT NULL,
    status ENUM('pending','approved','spam') NOT NULL DEFAULT 'approved' COMMENT 'Moderation state; new public comments default to pending in app layer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_comment_parent FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_post (post_id),
    INDEX idx_user (user_id),
    INDEX idx_comment_post_created (post_id, created_at),
    INDEX idx_comment_user_created (user_id, created_at),
    INDEX idx_comment_status (status),
    INDEX idx_comment_post_status (post_id, status),
    INDEX idx_comment_parent (parent_comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User comments on published posts';

-- ----------------------------------------------------------------------------
-- Post Likes Table
-- ----------------------------------------------------------------------------
-- We store one row per user per liked post so likes can be toggled and
-- counted cheaply; the unique key makes concurrent toggles race-safe.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_like_post_user (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_like_post (post_id),
    INDEX idx_like_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per user per liked post';

-- ----------------------------------------------------------------------------
-- Post Bookmarks Table
-- ----------------------------------------------------------------------------
-- Same shape as post_likes; kept separate so the two signals stay independent
-- and each can be indexed and queried on its own.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS post_bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bookmark_post_user (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bookmark_post (post_id),
    INDEX idx_bookmark_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per user per saved post';

-- ----------------------------------------------------------------------------
-- Submissions Table
-- ----------------------------------------------------------------------------
-- We track contributor submissions separately from posts to maintain a clear
-- distinction between accepted content and pending ideas.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    contributor_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL COMMENT 'Additional notes from contributor to reviewers',
    status ENUM('submitted','under_review','accepted','rejected') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (contributor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_blog (blog_id),
    INDEX idx_contributor (contributor_id),
    INDEX idx_status (status),
    INDEX idx_reviewed_by (reviewed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Content submissions from contributors for review';

-- ----------------------------------------------------------------------------
-- Role Permissions Junction Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role (role_id),
    INDEX idx_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Many-to-many relationship between roles and permissions';

-- ----------------------------------------------------------------------------
-- User Roles Junction Table
-- ----------------------------------------------------------------------------
-- We track who assigned each role to maintain accountability and enable
-- auditing of role changes over time.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT DEFAULT NULL COMMENT 'User ID who assigned this role',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_role (user_id, role_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Many-to-many relationship between users and roles';

-- ----------------------------------------------------------------------------
-- Activity Log Table
-- ----------------------------------------------------------------------------
-- We log all significant user actions to enable auditing, debugging, and
-- compliance with data protection regulations.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) DEFAULT NULL COMMENT 'Type of resource affected (post, user, blog, etc)',
    resource_id INT DEFAULT NULL COMMENT 'ID of the affected resource',
    details TEXT DEFAULT NULL COMMENT 'JSON or text details about the action',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comprehensive audit log of user actions';

-- ----------------------------------------------------------------------------
-- Data Export Requests Table
-- ----------------------------------------------------------------------------
-- We track GDPR-compliant data export requests with status tracking to
-- enable asynchronous processing of potentially large exports.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS data_export_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('requested','processing','completed','failed') NOT NULL DEFAULT 'requested',
    file_path VARCHAR(512) DEFAULT NULL COMMENT 'Path to generated export file',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User data export requests for GDPR compliance';

-- ----------------------------------------------------------------------------
-- Account Deletion Requests Table
-- ----------------------------------------------------------------------------
-- We implement a multi-step deletion process with confirmation to prevent
-- accidental account loss and comply with right-to-erasure regulations.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS account_deletion_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('requested','confirmed','processed','canceled') NOT NULL DEFAULT 'requested',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Account deletion requests with multi-step confirmation';

-- ----------------------------------------------------------------------------
-- Password Resets Table
-- ----------------------------------------------------------------------------
-- We use time-limited tokens for secure password recovery without storing
-- passwords in plaintext or reversible encryption.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(255) NOT NULL COMMENT 'Cryptographically secure random token',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Time-limited password reset tokens';

-- ----------------------------------------------------------------------------
-- Pages Table
-- ----------------------------------------------------------------------------
-- Admin managed static pages (about, contact, legal texts, guides). Pages
-- exist per locale; English acts as the fallback for missing translations.
-- Slugs are only reachable through explicit routes.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL,
    locale VARCHAR(5) NOT NULL DEFAULT 'en',
    title VARCHAR(200) NOT NULL,
    content LONGTEXT,
    meta_description VARCHAR(160) DEFAULT NULL,
    thumbnail_path VARCHAR(255) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_slug_locale (slug, locale),
    INDEX idx_slug (slug),
    INDEX idx_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Admin managed static pages (about, contact, legal, guides)';

-- ----------------------------------------------------------------------------
-- Site Content Table
-- ----------------------------------------------------------------------------
-- Per-locale overrides for the editable public site text (front page, footer,
-- sidebar). Locale files remain the defaults; an empty locale string marks a
-- value shared by every locale (contact details, social URLs).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    locale VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'Empty string means the value applies to every locale',
    value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name_locale (name, locale),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Editable site text; overrides the locale file defaults';

-- ============================================================================
-- ADD FOREIGN KEY FOR USER PREFERENCES (After blogs table exists)
-- ============================================================================

ALTER TABLE user_preferences
    ADD CONSTRAINT fk_user_pref_default_blog
    FOREIGN KEY (default_blog_id)
    REFERENCES blogs(id)
    ON DELETE SET NULL;

-- ============================================================================
-- SEED DATA - ROLES AND PERMISSIONS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Seed Roles
-- ----------------------------------------------------------------------------
-- We define a hierarchical role structure from Administrator (level 100) down
-- to Contributor (level 20) to enable role-based access control.
-- ----------------------------------------------------------------------------
INSERT INTO roles (role_name, role_slug, description, scope, is_system, level) VALUES
('Administrator', 'administrator', 'Full site-wide access including user management and site settings', 'system', 1, 100),
('Content Manager', 'content_manager', 'Can edit and manage all posts across the entire site', 'system', 1, 80),
('Blog Owner', 'blog_owner', 'Owns and manages a specific blog, can assign Authors', 'blog', 1, 60),
('Author', 'author', 'Can create and edit posts within assigned blog(s)', 'blog', 1, 40),
('Reviewer', 'reviewer', 'Can review drafts and provide feedback, cannot edit or publish', 'blog', 1, 30),
('Contributor', 'contributor', 'Can submit drafts or ideas for review', 'blog', 1, 20);

-- ----------------------------------------------------------------------------
-- Seed Permissions
-- ----------------------------------------------------------------------------
-- We organize permissions by resource and action for consistency and to
-- enable dynamic permission checking in the application layer.
-- ----------------------------------------------------------------------------
INSERT INTO permissions (permission_name, permission_slug, resource, action, description) VALUES
-- User Management Permissions
('Manage All Users', 'manage_all_users', 'users', 'manage', 'Full user management including creation, editing, and deletion'),
('Create Users', 'create_users', 'users', 'create', 'Create new user accounts'),
('Edit Users', 'edit_users', 'users', 'update', 'Edit existing user accounts'),
('Delete Users', 'delete_users', 'users', 'delete', 'Delete user accounts'),
('View Users', 'view_users', 'users', 'read', 'View user information'),

-- Site Settings Permissions
('Manage Site Settings', 'manage_site_settings', 'settings', 'manage', 'Modify application-wide settings'),

-- Blog Management Permissions
('Create Blogs', 'create_blogs', 'blogs', 'create', 'Create new blogs'),
('Edit Own Blog', 'edit_own_blog', 'blogs', 'update_own', 'Edit blogs you own'),
('Delete Own Blog', 'delete_own_blog', 'blogs', 'delete_own', 'Delete blogs you own'),
('View All Blogs', 'view_all_blogs', 'blogs', 'read', 'View all blogs on the platform'),
('Assign Authors', 'assign_authors', 'blogs', 'assign', 'Assign authors to blogs'),

-- Post Management (All Posts) Permissions
('Manage All Posts', 'manage_all_posts', 'posts', 'manage', 'Full management of all posts site-wide'),
('Edit All Posts', 'edit_all_posts', 'posts', 'update_all', 'Edit any post regardless of author'),
('Delete All Posts', 'delete_all_posts', 'posts', 'delete_all', 'Delete any post regardless of author'),
('Publish All Posts', 'publish_all_posts', 'posts', 'publish_all', 'Publish any post regardless of author'),

-- Post Management (Own/Blog Posts) Permissions
('Create Posts', 'create_posts', 'posts', 'create', 'Create new posts'),
('Edit Own Posts', 'edit_own_posts', 'posts', 'update_own', 'Edit your own posts'),
('Delete Own Posts', 'delete_own_posts', 'posts', 'delete_own', 'Delete your own posts'),
('Publish Own Posts', 'publish_own_posts', 'posts', 'publish_own', 'Publish your own posts'),
('Edit Blog Posts', 'edit_blog_posts', 'posts', 'update_blog', 'Edit posts in blogs you manage'),
('Delete Blog Posts', 'delete_blog_posts', 'posts', 'delete_blog', 'Delete posts in blogs you manage'),
('Publish Blog Posts', 'publish_blog_posts', 'posts', 'publish_blog', 'Publish posts in blogs you manage'),
('View All Posts', 'view_all_posts', 'posts', 'read', 'View all posts including drafts'),

-- Review and Feedback Permissions
('Review Posts', 'review_posts', 'posts', 'review', 'Access posts for review'),
('Approve Posts', 'approve_posts', 'posts', 'approve', 'Approve posts for publication'),
('Reject Posts', 'reject_posts', 'posts', 'reject', 'Reject posts and request changes'),
('Provide Feedback', 'provide_feedback', 'posts', 'feedback', 'Provide feedback on posts'),

-- Submissions Permissions
('Submit Ideas', 'submit_ideas', 'submissions', 'create', 'Submit content ideas for review'),
('View Own Submissions', 'view_own_submissions', 'submissions', 'read_own', 'View your own submissions'),
('Review Submissions', 'review_submissions', 'submissions', 'review', 'Review and approve/reject submissions'),

-- Control Panel Area Permissions
-- Administrators pass every Gate check by role; these let other roles be
-- granted individual admin areas (e.g. a moderator holding moderate_comments).
('Access Control Panel', 'access_control_panel', 'admin', 'read', 'Open the control panel dashboard'),
('Manage All Blogs', 'manage_all_blogs', 'blogs', 'manage', 'Full blog management in the control panel'),
('Moderate Comments', 'moderate_comments', 'comments', 'manage', 'Approve, unapprove, mark spam, and delete comments'),
('Manage Taxonomy', 'manage_taxonomy', 'taxonomy', 'manage', 'Manage categories and tags in the control panel'),
('Manage Roles', 'manage_roles', 'roles', 'manage', 'Create custom roles and edit role permissions'),
('View Audit Log', 'view_audit_log', 'audit', 'read', 'Read the audit trail'),
('View System Health', 'view_system_health', 'system', 'read', 'View system diagnostics'),
('Manage Cache', 'manage_cache', 'cache', 'manage', 'View cache statistics, prune and clear caches');

-- ----------------------------------------------------------------------------
-- Assign Permissions to Administrator Role
-- ----------------------------------------------------------------------------
-- We grant all permissions to administrators for complete system access.
-- ----------------------------------------------------------------------------
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- ----------------------------------------------------------------------------
-- Assign Permissions to Content Manager Role
-- ----------------------------------------------------------------------------
-- We grant content managers full post management but limited user access to
-- maintain separation of concerns between content and user administration.
-- ----------------------------------------------------------------------------
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions 
WHERE permission_slug IN (
    'view_users', 'view_all_blogs', 'manage_all_posts', 'edit_all_posts', 
    'delete_all_posts', 'publish_all_posts', 'view_all_posts',
    'review_posts', 'approve_posts', 'reject_posts', 'provide_feedback',
    'review_submissions'
);

-- ----------------------------------------------------------------------------
-- Assign Permissions to Blog Owner Role
-- ----------------------------------------------------------------------------
-- We grant blog owners full control over their blogs and associated content
-- including the ability to manage blog membership.
-- ----------------------------------------------------------------------------
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions 
WHERE permission_slug IN (
    'create_blogs', 'edit_own_blog', 'delete_own_blog', 'view_all_blogs',
    'assign_authors', 'create_posts', 'edit_blog_posts', 'delete_blog_posts',
    'publish_blog_posts', 'view_all_posts', 'review_posts', 'approve_posts',
    'reject_posts', 'provide_feedback', 'review_submissions'
);

-- ----------------------------------------------------------------------------
-- Assign Permissions to Author Role
-- ----------------------------------------------------------------------------
-- We grant authors the ability to create and manage their own content while
-- preventing them from publishing without approval where workflows require it.
-- ----------------------------------------------------------------------------
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions 
WHERE permission_slug IN (
    'create_posts', 'edit_own_posts', 'delete_own_posts', 
    'publish_own_posts', 'view_all_posts', 'view_own_submissions'
);

-- ----------------------------------------------------------------------------
-- Assign Permissions to Reviewer Role
-- ----------------------------------------------------------------------------
-- We grant reviewers read and feedback permissions without edit capabilities
-- to maintain editorial separation between authorship and review.
-- ----------------------------------------------------------------------------
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions 
WHERE permission_slug IN (
    'view_all_posts', 'review_posts', 'approve_posts', 'reject_posts',
    'provide_feedback', 'review_submissions'
);

-- ----------------------------------------------------------------------------
-- Assign Permissions to Contributor Role
-- ----------------------------------------------------------------------------
-- We grant contributors minimal permissions focused on submitting ideas for
-- consideration by authors or blog owners.
-- ----------------------------------------------------------------------------
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions 
WHERE permission_slug IN (
    'submit_ideas', 'view_own_submissions', 'view_all_posts'
);

-- ============================================================================
-- SEED DATA - RESERVED SLUGS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- System Reserved Slugs
-- ----------------------------------------------------------------------------
-- We reserve common system paths to prevent routing conflicts and maintain
-- clean URL structure for administrative and system functions.
-- ----------------------------------------------------------------------------
INSERT INTO reserved_slugs (slug, reason) VALUES
  ('admin',       'Reserved for admin area'),
  ('account',     'Reserved for account routes'),
  ('dashboard',   'Reserved for dashboards'),
  ('profile',     'Reserved base for profiles'),
  ('login',       'Reserved for login route'),
  ('logout',      'Reserved for logout route'),
  ('register',    'Reserved for registration route'),
  ('signup',      'Reserved for registration route'),
  ('settings',    'Reserved for generic settings'),
  ('api',         'Reserved for API routes'),
  ('blog',        'Reserved for blog routes'),
  ('blogs',       'Reserved for blog index'),
  ('posts',       'Reserved for posts index'),
  ('categories',  'Reserved for category routes'),
  ('tags',        'Reserved for tag routes'),
  ('search',      'Reserved for search route'),
  ('feed',        'Reserved for feeds'),
  ('rss',         'Reserved for feeds'),
  ('atom',        'Reserved for Atom feeds'),
  ('sitemap',     'Reserved for sitemap generation'),
  ('robots',      'Reserved for robots.txt'),
  ('me',          'Reserved for internal profile shortcut'),
  ('auth',        'Reserved for authentication routes'),
  ('oauth',       'Reserved for OAuth routes'),
  ('callback',    'Reserved for OAuth callbacks'),
  ('static',      'Reserved for static assets'),
  ('assets',      'Reserved for asset serving'),
  ('public',      'Reserved for public routes'),
  ('private',     'Reserved for private routes'),
  ('user',        'Reserved for user routes'),
  ('users',       'Reserved for user index')
ON DUPLICATE KEY UPDATE reason = VALUES(reason);

-- ============================================================================
-- FINALIZATION
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================