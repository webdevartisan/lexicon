<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\PostReviewerModel;
use Framework\Core\Response;

/**
 * Shared landing — every blog someone else invited me to.
 *
 * Renders a card per shared blog with stats and a CTA matched to the user's
 * role on that blog. Owned blogs are intentionally excluded: those live in
 * "All Blogs" / Dashboard, not here.
 */
final class SharedController extends AppController
{
    public function __construct(
        private BlogModel $blogModel,
        private PostModel $postModel,
        private PostReviewerModel $postReviewerModel,
    ) {}

    /**
     * Render the Shared landing page.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $userId = (int) $user['id'];

        $sharedBlogs = $this->blogModel->getSharedBlogsForUser($userId);

        $cards = array_map(
            fn (array $blog): array => $this->buildCard($blog, $userId),
            $sharedBlogs
        );

        return $this->view('shared.index', [
            'cards' => $cards,
            'hideTitle' => true,
            'noBreadcrumb' => true,
        ]);
    }

    /**
     * Assemble the role-tailored card payload for a single shared blog.
     *
     * Each card is uniform shape: identity (blog), role badge, a small stats
     * map, plus a primary CTA. The view only renders — no role-branching logic
     * in templates.
     *
     * @param  array<string,mixed>  $blog  Row from BlogModel::getSharedBlogsForUser
     * @return array<string,mixed>
     */
    private function buildCard(array $blog, int $userId): array
    {
        $blogId = (int) $blog['id'];
        $role   = (string) ($blog['user_role'] ?? 'reviewer');

        $stats = match ($role) {
            'editor'      => $this->statsForEditor($blogId),
            'reviewer'    => $this->statsForReviewer($blogId, $userId),
            'author'      => $this->statsForAuthor($blogId, $userId),
            'contributor' => $this->statsForContributor($blogId, $userId),
            default       => [],
        };

        // A short preview of items the user can actually act on, so the card
        // isn't just numbers. Reviewer/editor see the review queue; authors
        // see their own in-flight work on this blog.
        $items = match ($role) {
            'reviewer', 'editor' => array_slice($this->postModel->findReviewQueueForBlog($blogId), 0, 3),
            default              => [],
        };

        return [
            'blog'  => [
                'id'         => $blogId,
                'name'       => (string) ($blog['blog_name'] ?? 'Untitled'),
                'slug'       => (string) ($blog['blog_slug'] ?? ''),
                'status'     => (string) ($blog['status'] ?? 'draft'),
                'owner_name' => (string) ($blog['owner_name'] ?? ''),
            ],
            'role'  => $role,
            'stats' => $stats,
            'items' => $items,
            'actions' => $this->actionsForRole($role, $blogId),
        ];
    }

    /**
     * Editor sees the operational picture: incoming work + pending moderation.
     *
     * @return array<int,array{label:string,value:int,tone:string}>
     */
    private function statsForEditor(int $blogId): array
    {
        return [
            ['label' => 'To review',     'value' => $this->postModel->countByBlogAndWorkflow($blogId, 'in_review'),     'tone' => 'amber'],
            ['label' => 'Needs changes', 'value' => $this->postModel->countByBlogAndWorkflow($blogId, 'needs_changes'), 'tone' => 'slate'],
            ['label' => 'Pending comments', 'value' => $this->postModel->countCommentsByBlogIdAndStatus($blogId, 'pending'), 'tone' => 'sky'],
        ];
    }

    /**
     * Reviewer sees what's in flight on this blog: items waiting for review
     * plus items already bounced back with "needs changes" they should
     * verify when resubmitted.
     *
     * @return array<int,array{label:string,value:int,tone:string}>
     */
    private function statsForReviewer(int $blogId, int $userId): array
    {
        return [
            ['label' => 'Awaiting review', 'value' => $this->postModel->countByBlogAndWorkflow($blogId, 'in_review'),     'tone' => 'amber'],
            ['label' => 'Needs revisit',   'value' => $this->postModel->countByBlogAndWorkflow($blogId, 'needs_changes'), 'tone' => 'slate'],
        ];
    }

    /**
     * Author sees their personal pipeline on this blog.
     *
     * @return array<int,array{label:string,value:int,tone:string}>
     */
    private function statsForAuthor(int $blogId, int $userId): array
    {
        return [
            ['label' => 'Your drafts',    'value' => $this->postModel->countByBlogAndAuthorAndStatus($blogId, $userId, 'draft'),    'tone' => 'slate'],
            ['label' => 'Submitted',      'value' => $this->postModel->countByBlogAndAuthorAndWorkflow($blogId, $userId, 'in_review'),     'tone' => 'sky'],
            ['label' => 'Needs revision', 'value' => $this->postModel->countByBlogAndAuthorAndWorkflow($blogId, $userId, 'needs_changes'), 'tone' => 'amber'],
        ];
    }

    /**
     * Contributor — lighter than author; no revision feedback loop unless they
     * have something needing changes (rare but possible).
     *
     * @return array<int,array{label:string,value:int,tone:string}>
     */
    private function statsForContributor(int $blogId, int $userId): array
    {
        return [
            ['label' => 'Your drafts', 'value' => $this->postModel->countByBlogAndAuthorAndStatus($blogId, $userId, 'draft'),    'tone' => 'slate'],
            ['label' => 'Submitted',   'value' => $this->postModel->countByBlogAndAuthorAndWorkflow($blogId, $userId, 'in_review'),  'tone' => 'sky'],
        ];
    }

    /**
     * Per-role action set — primary plus a secondary "Open blog" so every card
     * has both a job-to-do path and an at-a-glance overview path.
     *
     * @return array{primary:array{label:string,href:string,icon:string,variant:string},secondary:array{label:string,href:string,icon:string}}
     */
    private function actionsForRole(string $role, int $blogId): array
    {
        $primary = match ($role) {
            'editor' => [
                'label'   => 'Open review queue',
                'href'    => "/dashboard/blog/{$blogId}/review-queue",
                'icon'    => 'clipboard-check',
                'variant' => 'blue',
            ],
            'reviewer' => [
                'label'   => 'Open review queue',
                'href'    => "/dashboard/blog/{$blogId}/review-queue",
                'icon'    => 'clipboard-check',
                'variant' => 'blue',
            ],
            'author' => [
                'label'   => 'Write a post',
                'href'    => "/dashboard/post/new?blog_id={$blogId}",
                'icon'    => 'pen',
                'variant' => 'blue',
            ],
            'contributor' => [
                'label'   => 'Draft a post',
                'href'    => "/dashboard/post/new?blog_id={$blogId}",
                'icon'    => 'pen',
                'variant' => 'blue',
            ],
            default => [
                'label'   => 'Open blog',
                'href'    => "/dashboard/blog/{$blogId}/show",
                'icon'    => 'arrow-right',
                'variant' => 'blue',
            ],
        };

        return [
            'primary' => $primary,
            'secondary' => [
                'label' => 'Blog overview',
                'href'  => "/dashboard/blog/{$blogId}/show",
                'icon'  => 'layout-grid',
            ],
        ];
    }
}
