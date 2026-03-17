<?php

declare(strict_types=1);

namespace App\Policies;

use App\Resources\PostResource;
use Framework\Interfaces\PolicyInterface;

/**
 * PostPolicy
 *
 * We control who may view, edit, publish, or delete an individual post.
 * Structural blog owner is always allowed; per-blog roles define collaboration.
 */
final class PostPolicy implements PolicyInterface
{
    /**
     * View a post in dashboard context.
     */
    public function view(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $userRole = $blog->roleForUser((int) $user['id']);
        $allowedRoles = ['editor', 'author', 'contributor', 'reviewer', 'viewer'];

        return in_array($userRole, $allowedRoles, true);
    }

    /**
     * Update a post's content/metadata.
     * Owner and editors can edit any post; authors can edit only their own.
     */
    public function update(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $role = $blog->roleForUser((int) $user['id']);

        if ($role === 'editor') {
            return true;
        }

        // should allow authors to edit their own posts but not others'.
        return $role === 'author' && $post->authorId() === (int) $user['id'];
    }

    /**
     * Publish or unpublish a post (change visibility).
     * Only owner or editor can do this.
     */
    public function publish(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $role = $blog->roleForUser((int) $user['id']);

        return $role === 'editor';
    }

    /**
     * Delete a post.
     * Owner or editors; optionally allow authors to delete their own drafts only.
     */
    public function delete(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $role = $blog->roleForUser((int) $user['id']);

        if ($role === 'editor') {
            return true;
        }

        // Optional: authors may delete only their own un-published posts.
        if ($role === 'author' && $post->authorId() === (int) $user['id']) {
            return $post->status() === 'draft';
        }

        return false;
    }

    public function markAsNeedsChanges(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $userRole = $blog->roleForUser((int) $user['id']);
        $allowedRoles = ['reviewer'];

        return in_array($userRole, $allowedRoles, true);
    }

    public function approve(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $userRole = $blog->roleForUser((int) $user['id']);
        $allowedRoles = ['reviewer'];

        return in_array($userRole, $allowedRoles, true);
    }
}
