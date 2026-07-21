<?php

declare(strict_types=1);

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
    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    private function hasRole(array $user, string $role): bool
    {
        return in_array($role, $user['roles'] ?? [], true);
    }

    /**
     * View a blog in dashboard context.
     * Owner always allowed; per-blog roles editor/author/viewer/contributor/reviewer can view.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
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

    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function create(array $user): bool
    {
        // 'reader' is here on purpose: any registered account may start a blog.
        // The creator upgrade (author role) happens on first successful creation.
        $allowedRoles = ['administrator', 'editor', 'author', 'content_manager', 'blog_owner', 'reader'];
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
     *
     * @param  array<string, mixed>  $user  Authenticated user record
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
     * Owner only — editors no longer manage the collaborator roster.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageUsers(array $user, object $blog): bool
    {
        return $blog->ownerId() === $user['id'];
    }

    /**
     * Invite a new collaborator to this blog.
     * Owner only.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function invite(array $user, object $blog): bool
    {
        return $blog->ownerId() === $user['id'];
    }

    /**
     * Create a post in this blog.
     * Owner, editor, author, contributor can start a post.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
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
     *
     * @param  array<string, mixed>  $user  Authenticated user record
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
