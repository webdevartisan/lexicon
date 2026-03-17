## Database Schema Overview

Lexicon uses a relational schema optimized for a multi‑blog platform with roles, permissions, collaborators, and content workflow. The canonical definition lives in `database/migrations/2026_02_02_install.sql`.

---

## Core Tables

- **`users`**
  - Authentication and identity data (username, email, password).
  - Denormalized counters: `posts_count`, `comments_received_count`.
  - Soft deletes via `deleted_at`.

- **`roles`**
  - Role definitions such as administrator and blog‑level roles.
  - Columns: `role_name`, `role_slug`, `description`, `level` (higher = more authority).

- **`permissions`**
  - Capability definitions used by policies and authorization.
  - Columns: `permission_name`, `permission_slug`, `resource`, `action`.

- **`settings`**
  - Application‑wide key/value configuration.

- **`reserved_slugs`**
  - Registry of reserved slugs to avoid conflicts with routing and system endpoints.

- **`migrations`**
  - Tracks executed migrations by filename and timestamp.

---

## User‑Related Tables

- **`user_profiles`**
  - One‑to‑one with `users` via `user_id` (PK and FK).
  - Public profile fields: `slug`, `bio`, `avatar_url`, `location`, `occupation`, `is_public`.

- **`user_social_links`**
  - One‑to‑many from `users`: multiple social accounts per user.
  - Columns: `user_id`, `network`, `url`.

- **`user_preferences`**
  - One‑to‑one with `users` for personalization.
  - Columns: `default_blog_id`, `display_name_preference`, `default_post_visibility`, `timezone`, notification flags.

---

## Blogging Tables

- **`blogs`**
  - Represents an individual blog.
  - Columns:
    - `blog_name`, `blog_slug`, `description`
    - `owner_id` (FK to `users`)
    - `status` (`draft`, `published`, `archived`)
    - `published_at`, `archived_at`

- **`blog_settings`**
  - One‑to‑one with `blogs`.
  - Stores theme and localization defaults: `theme`, `default_locale`, plus any blog‑specific options.

- **`blog_users`** (collaborators)
  - Many‑to‑many link between `blogs` and `users`.
  - Stores collaboration metadata such as role and invitation status.
  - Used by policies to determine who can manage and publish content for a blog.

---

## Taxonomy and Content Tables

- **`categories`**
  - Simple category list: `name`, `slug`.

- **`tags`**
  - Flexible tagging: `name`, `slug`.

- **`posts`**
  - Blog posts with workflow support.
  - Key columns (see the migration for full list):
    - `blog_id` (FK to `blogs`)
    - `author_id` (FK to `users`)
    - `category_id` (FK to `categories`, nullable)
    - `title`, `slug`, `excerpt`, `content`
    - Visibility and workflow fields (e.g. status/state, timestamps)

- **`post_tags`**
  - Pivot table linking posts to tags.
  - Columns: `post_id`, `tag_id`.

- **`comments`**
  - Comments on posts.
  - Columns: `post_id`, `user_id` (or guest info), `content`, timestamps, moderation fields.

---

## Supporting Tables

- **Password reset / security tables**
  - Tables such as `password_resets` or equivalent are used by `PasswordResetModel` and related services.

Consult the migration file for exact column definitions, indexes, and constraints. See `docs/database/relationships.md` for how these tables relate at the model level.

