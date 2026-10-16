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
     * the account's system roles must grant create_blogs. Readers hold it by
     * default, so an administrator can close sign-up blogging from the role
     * editor without touching code.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function create(array $user): bool
    {
        return in_array('administrator', $user['roles'] ?? [], true)
            || in_array('create_blogs', $user['permissions'] ?? [], true);
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
     * Hand the blog to someone else. Only the owner, since it gives away their own blog.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function transferOwnership(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->ownerId() === (int) $user['id'];
    }

    /**
     * Download the blog's archive or import one into it. Only the owner, never a
     * delegated role: an export is every post including drafts, and an import can
     * overwrite them.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageArchive(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->ownerId() === (int) $user['id'];
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
     * Work on every post in the blog, not only one's own: the whole-blog Posts
     * list and the blog's numbers on the Overview. Owner and editor.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function managePosts(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'edit_blog_posts');
    }

    /**
     * Open the Posts page at all: anyone who writes here or manages what others wrote.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function listPosts(array $user, object $blog): bool
    {
        return $this->createPost($user, $blog) || $this->managePosts($user, $blog);
    }

    /**
     * Open the blog's review queue. Owner, editor and reviewer.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function reviewPosts(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'review_posts');
    }

    /**
     * Choose who a post in this blog is credited to. The same permission that
     * lets someone edit every post in the blog, so owner and editor only.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function assignPostAuthor(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'edit_blog_posts');
    }

    /**
     * Open the blog's Insights in the dashboard: anyone who writes for the blog.
     * Platform staff read a blog's numbers from the control panel instead.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function viewAnalytics(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'create_posts')
            || $blog->userCan((int) $user['id'], 'edit_blog_posts');
    }

    /**
     * See the whole blog's numbers, not only one's own posts.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function viewAllAnalytics(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $blog->userCan((int) $user['id'], 'edit_blog_posts');
    }

    /**
     * The Authors page: whole-blog numbers, on a blog where more than one person
     * has published, since with one author it would only repeat the other pages.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function viewAuthorInsights(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        return $this->viewAllAnalytics($user, $blog) && $blog->publishedAuthorCount() >= 2;
    }

    /**
     * Delete the blog. Owner only: an admin can put delete_own_blog on a custom
     * collaborator role, and that must not let a collaborator delete someone
     * else's blog.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function delete(array $user, object $blog): bool
    {
        assert($blog instanceof BlogResource);

        $uid = (int) $user['id'];

        return $blog->ownerId() === $uid && $blog->userCan($uid, 'delete_own_blog');
    }
}
