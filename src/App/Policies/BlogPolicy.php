<?php

namespace App\Policies;

use Framework\Interfaces\PolicyInterface;

/**
 * BlogPolicy
 *
 * We use this policy for actions that are scoped to the blog itself:
 * viewing, updating identity/settings, deleting, and creating posts in this blog.
 */
class BlogPolicy implements PolicyInterface
{
    private function hasRole(array $user, string $role): bool
    {
        return in_array($role, $user['roles'] ?? [], true);
    }

    /**
     * View a blog in dashboard context.
     * Owner always allowed; per-blog roles editor/author/viewer/contributor/reviewer can view.
     */
    public function view(array $user, object $blog): bool
    {
        // owner always allowed
        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        // if ($this->hasRole($user, 'administrator')) {
        //     return true;
        // }

        // per-blog roles: editor, author, viewer can view
        $blogRole = $blog->roleForUser((int) $user['id']); // method on BlogResource
        $allowedRoles = ['editor', 'author', 'viewer', 'contributor', 'reviewer'];

        return in_array($blogRole, $allowedRoles, true);
    }

    public function create(array $user): bool
    {
        $allowedRoles = ['administrator', 'editor', 'author', 'content_manager', 'blog_owner'];
        foreach ($allowedRoles as $role) {
            if ($this->hasRole($user, $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update blog identity/settings.
     * Owner or editor per-blog.
     */
    public function update(array $user, object $blog): bool
    {
        // owner always allowed
        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        /*
        if ($this->hasRole($user, 'administrator')) {
            return true;
        }*/

        // per-blog: editors can update
        $blogRole = $blog->roleForUser((int) $user['id']);

        return $blogRole === 'editor';
    }

    /**
     * Manage users attached to this blog.
     * Owner or editor per-blog.
     */
    public function manageUsers(array $user, object $blog): bool
    {
        if ($blog->ownerId() === $user['id']) {
            return true;
        }
        $blogRole = $blog->roleForUser((int) $user['id']);

        return $blogRole === 'editor';
    }

    /**
     * Create a post in this blog.
     * Owner, editor, author, contributor can start a post.
     */
    public function createPost(array $user, object $blog): bool
    {
        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $blogRole = $blog->roleForUser((int) $user['id']);

        return in_array($blogRole, ['editor', 'author', 'contributor'], true);
    }

    /**
     * Delete blog.
     * Keep strict: owner only.
     */
    public function delete(array $user, object $blog): bool
    {
        // strict: only owner , ignore per-blog roles
        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        return false;
    }
}
