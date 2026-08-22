<?php

declare(strict_types=1);

namespace App\Policies;

use App\Resources\PostResource;
use Framework\Interfaces\PolicyInterface;

/**
 * PostPolicy
 *
 * Controls who may view, edit, publish, assign reviewers, or perform review
 * actions on a post. Every decision is driven by the acting user's blog-level
 * permissions on the post's blog (BlogResource::userCan). Structural owners
 * hold the full owner bundle, so they are not special-cased. Ownership and
 * workflow-state guards (own posts only, in-review lock, draft-only delete)
 * layer on top of the permission check.
 */
final class PostPolicy implements PolicyInterface
{
    /**
     * View a post in dashboard context. Any collaborator with read access.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function view(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        return $post->blog()->userCan((int) $user['id'], 'view_all_posts');
    }

    /**
     * Update a post's content/metadata.
     *
     * Editors (and owner) edit any post via edit_blog_posts. Authors and
     * contributors edit their own posts via edit_own_posts, but are locked out
     * while the post is in review.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function update(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $uid = (int) $user['id'];
        $blog = $post->blog();

        if ($blog->userCan($uid, 'edit_blog_posts')) {
            return true;
        }

        if ($blog->userCan($uid, 'edit_own_posts')) {
            // Authors and contributors are locked out while a post is in review.
            if ($post->workflowState() === 'in_review') {
                return false;
            }

            return $post->authorId() === $uid;
        }

        return false;
    }

    /**
     * Publish or unpublish a post. Owner or editor.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function publish(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        return $post->blog()->userCan((int) $user['id'], 'publish_blog_posts');
    }

    /**
     * Delete a post. Owner or editor delete any; authors delete only their own
     * un-published (draft) posts.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function delete(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        $uid = (int) $user['id'];
        $blog = $post->blog();

        if ($blog->userCan($uid, 'delete_blog_posts')) {
            return true;
        }

        if ($blog->userCan($uid, 'delete_own_posts') && $post->authorId() === $uid) {
            return $post->status() === 'draft';
        }

        return false;
    }

    /**
     * Mark a post as needing changes (send back to the author for revision).
     * Owner, editor, and reviewer.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function markAsNeedsChanges(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        return $post->blog()->userCan((int) $user['id'], 'reject_posts');
    }

    /**
     * Approve a post (advance to approved state). Owner, editor, and reviewer.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function approve(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        return $post->blog()->userCan((int) $user['id'], 'approve_posts');
    }

    /**
     * Assign a reviewer to a post. Owner and editor assign any reviewer;
     * reviewers may self-assign (WorkflowService enforces the self-assign
     * constraint — the policy only checks reviewer capability or above).
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function assignReviewer(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        return $post->blog()->userCan((int) $user['id'], 'assign_reviewers');
    }

    /**
     * Perform a review action (approve or request changes) on a post.
     * Owner, editor, and reviewer.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function reviewPost(array $user, object $post): bool
    {
        assert($post instanceof PostResource);

        return $post->blog()->userCan((int) $user['id'], 'review_posts');
    }
}
