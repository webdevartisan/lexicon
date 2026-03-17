## Database Relationships

This document summarizes how the main tables in `database/migrations/2026_02_02_install.sql` relate to each other and how they map to models under `App\Models`.

---

## Users and Profiles

- **`users` ↔ `user_profiles`**
  - 1:1 via `user_profiles.user_id` (PK and FK).
  - Models: `UserModel`, `UserProfileModel`.
  - Used for public profile pages and extended information.

- **`users` ↔ `user_social_links`**
  - 1:N via `user_social_links.user_id`.
  - Model: `UserSocialLinkModel`.
  - Each row represents one social network URL per user.

- **`users` ↔ `user_preferences`**
  - 1:1 via `user_preferences.user_id` (PK and FK).
  - Model: `UserPreferencesModel`.
  - Holds default blog, timezone, notification flags, and visibility defaults.

---

## Blogs, Owners, and Collaborators

- **`users` ↔ `blogs`**
  - 1:N via `blogs.owner_id`.
  - Models: `UserModel`, `BlogModel`.
  - The owner has full control over the blog.

- **`blogs` ↔ `blog_settings`**
  - 1:1 via `blog_settings.blog_id` (PK and FK).
  - Models: `BlogModel`, `BlogSettingsModel`.
  - Used for theme and localization configuration.

- **`blogs` ↔ `users` (collaborators) via `blog_users`**
  - N:M via pivot table `blog_users`.
  - Model side: `BlogModel` exposes collaborator access; policies use this to enforce permissions.
  - Stores collaboration attributes (role, status, timestamps).

---

## Content: Posts, Categories, Tags, Comments

- **`blogs` ↔ `posts`**
  - 1:N via `posts.blog_id`.
  - Models: `BlogModel`, `PostModel`.
  - Each post belongs to a single blog.

- **`users` ↔ `posts`**
  - 1:N via `posts.author_id`.
  - Models: `UserModel`, `PostModel`.
  - Used for author feeds and permissions.

- **`categories` ↔ `posts`**
  - 1:N via `posts.category_id`.
  - Models: `CategoryModel`, `PostModel`.
  - Optional; posts can be uncategorized.

- **`tags` ↔ `posts` via `post_tags`**
  - N:M via pivot table:
    - `post_tags.post_id` → `posts.id`
    - `post_tags.tag_id` → `tags.id`
  - Models: `TagModel`, `PostModel`.
  - Used for tag filters and tag clouds.

- **`posts` ↔ `comments`**
  - 1:N via `comments.post_id`.
  - Models: `PostModel`, `CommentModel`.
  - Comments may optionally link back to `users` for authenticated commenters.

---

## Roles and Permissions

- **`roles`**
  - Global role records with a `role_slug` and `level`.
  - Model: `RoleModel`.

- **`permissions`**
  - Resource/action‑oriented permission records.
  - Model: `PermissionModel`.

Policies such as `BlogPolicy`, `PostPolicy`, and `UserPolicy` rely on:

- Global roles from `roles`
- Per‑blog collaborator relationships from `blog_users`
- Capability checks derived from `permissions`

---

## Reserved Slugs and Routing

- **`reserved_slugs`**
  - Prevents collisions between user/blog slugs and system routes.
  - Model: `ReservedSlugModel`.
  - Used during validation when generating slugs for blogs, posts, or profiles.

---

## Model Mapping

Representative mapping between tables and models:

- `users` → `UserModel`
- `user_profiles` → `UserProfileModel`
- `user_preferences` → `UserPreferencesModel`
- `user_social_links` → `UserSocialLinkModel`
- `blogs` → `BlogModel`
- `blog_settings` → `BlogSettingsModel`
- `blog_users` → collaborator logic (via `BlogModel` + policies)
- `posts` → `PostModel`
- `comments` → `CommentModel`
- `categories` → `CategoryModel`
- `tags` → `TagModel`
- `post_tags` → pivot used by `PostModel`/`TagModel`
- `roles` → `RoleModel`
- `permissions` → `PermissionModel`
- `settings` → `SettingModel`
- `reserved_slugs` → `ReservedSlugModel`

For exact column definitions and foreign keys, refer to `docs/database/schema.md` and `database/migrations/2026_02_02_install.sql`.

