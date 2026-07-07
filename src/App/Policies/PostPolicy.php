<?php

declare(strict_types=1);

namespace App\Policies;

use App\Resources\PostResource;
use Framework\Interfaces\PolicyInterface;

/**
 * PostPolicy
 *
 * Controls who may view, edit, publish, assign reviewers, or perform
 * review actions on a post. Structural blog owner is always allowed;
 * per-blog roles define collaboration permissions.
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
        $allowedRoles = ['editor', 'author', 'contributor', 'reviewer'];

        return in_array($userRole, $allowedRoles, true);
    }

    /**
     * Update a post's content/metadata.
     *
     * Owner and editors can edit any post. Authors/contributors can edit their
     * own posts — unless the post is currently in_review (in-review lock).
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

        // Authors and contributors are locked out while a post is in review.
        if (in_array($role, ['author', 'contributor'], true)) {
            if ($post->workflowState() === 'in_review') {
                return false;
            }

            return $post->authorId() === (int) $user['id'];
        }

        return false;
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
     * Owner or editors always; authors may delete only their own un-published posts.
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

        if ($role === 'author' && $post->authorId() === (int) $user['id']) {
            return $post->status() === 'draft';
        }

        return false;
    }

    /**
     * Mark a post as needing changes (send back to author for revision).
     * Owner, editor, and reviewer may do this.
     */
    public function markAsNeedsChanges(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $userRole = $blog->roleForUser((int) $user['id']);

        return in_array($userRole, ['editor', 'reviewer'], true);
    }

    /**
     * Approve a post (advance to approved state).
     * Owner, editor, and reviewer may do this.
     */
    public function approve(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $userRole = $blog->roleForUser((int) $user['id']);

        return in_array($userRole, ['editor', 'reviewer'], true);
    }

    /**
     * Assign a reviewer to a post.
     *
     * Owner and editor may assign any reviewer. Reviewer role may self-assign
     * (the WorkflowService enforces the self-assign constraint — the policy
     * only checks that the acting user has reviewer capability or above).
     */
    public function assignReviewer(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $userRole = $blog->roleForUser((int) $user['id']);

        return in_array($userRole, ['editor', 'reviewer'], true);
    }

    /**
     * Perform a review action (approve or request changes) on a post.
     * Owner, editor, and reviewer can take review actions.
     */
    public function reviewPost(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $blog = $post->blog();

        if ($blog->ownerId() === $user['id']) {
            return true;
        }

        $userRole = $blog->roleForUser((int) $user['id']);

        return in_array($userRole, ['editor', 'reviewer'], true);
    }
}
