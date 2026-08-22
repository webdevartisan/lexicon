<?php

declare(strict_types=1);

namespace App\Policies;

use App\Resources\BlogResource;
use Framework\Interfaces\PolicyInterface;

/**
 * BlogPolicy
 *
 * Actions scoped to a blog itself: viewing, updating identity/settings,
 * deleting, managing the team, and creating posts in this blog.
 *
 * Every per-blog decision is driven by the acting user's blog-level
 * permissions (BlogResource::userCan), resolved from the role they hold on
 * this blog. Structural owners implicitly hold the full owner bundle, so they
 * are not special-cased here. Custom admin-created blog roles work natively
 * because they carry their own permissions.
 */
class BlogPolicy implements PolicyInterface
{
    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    private function hasRole(array $user, string $role): bool
    {
        return in_array($role, $user['roles'] ?? [], true);
    }

    /**
     * View a blog in dashboard context. Any collaborator with read access.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function view(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'view_all_posts');
    }

    /**
     * Start a new blog. This is a global (system) decision, not per-blog:
     * any registered account may create one. Readers start here and are
     * upgraded to a creator on their first blog.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function create(array $user): bool
    {
        foreach (['administrator', 'content_manager', 'reader'] as $role) {
            if ($this->hasRole($user, $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update blog identity/settings. Owner and editors.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function update(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'edit_own_blog');
    }

    /**
     * Manage the collaborator roster. Owner (and any custom delegated-owner
     * role holding manage_team); editors do not manage the roster.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageUsers(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'manage_team');
    }

    /**
     * Invite a new collaborator. Same gate as managing the roster.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function invite(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'manage_team');
    }

    /**
     * Create a post in this blog. Owner, editor, author, contributor.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function createPost(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'create_posts');
    }

    /**
     * Delete the blog. Owner only (delete_own_blog is in the owner bundle and
     * granted to no collaborator role).
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function delete(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'delete_own_blog');
    }
}
