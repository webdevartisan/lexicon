<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserPreferencesModel;
use Framework\Exceptions\PageNotFoundException;

/**
 * HomeController renders the dashboard overview: a true at-a-glance summary
 * (stats, needs-attention queues, recent activity, quick actions) — not a
 * filtered post list. The post-list-with-tabs role lives on All Posts.
 */
class HomeController extends AppController
{
    public function __construct(
        private PostModel $post,
        private BlogModel $blogModel,
        private UserPreferencesModel $preference
    ) {}

    /**
     * Dashboard overview for the active blog context.
     */
    public function index()
    {
        $user = auth()->user();
        $selectedBlogId = $this->preference->getDefaultBlogId($user['id']) ?? 0;

        $blogs = $this->blogModel->resource($user['id']);

        if (empty($blogs)) {
            return $this->view([
                'blogIds' => [],
                'blogSlug' => '',
                'selectedBlogId' => 0,
                'hasNoBlogs' => true,
                'stats' => $this->emptyStats(),
                'recent' => [],
                'needsAttention' => [],
                'blogsSummary' => [],
            ]);
        }

        $blogIds = [];
        $blogSlugs = [];

        foreach ($blogs as $blog) {
            $blogIds[$blog->id()] = $blog->name();
            $blogSlugs[$blog->id()] = $blog->slug();
        }

        if ($selectedBlogId <= 0 || !isset($blogSlugs[$selectedBlogId])) {
            return $this->view([
                'blogIds' => $blogIds,
                'blogSlug' => '',
                'selectedBlogId' => $selectedBlogId,
                'hasNoBlogs' => false,
                'stats' => $this->emptyStats(),
                'recent' => [],
                'needsAttention' => [],
                'blogsSummary' => $this->buildBlogsSummary($user['id']),
            ]);
        }

        $blog = $this->getBlog($selectedBlogId);
        Gate::authorize('view', $blog, $user);

        // Pull each status bucket once: we use the pagination total for stats,
        // and the first few rows as the "what's in this bucket" preview.
        $publishedResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'], page: 1, perPage: 4, blogId: $selectedBlogId, status: 'published'
        );
        $draftResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'], page: 1, perPage: 4, blogId: $selectedBlogId, status: 'draft'
        );
        $pendingResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'], page: 1, perPage: 4, blogId: $selectedBlogId, status: 'pending'
        );
        $archivedResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'], page: 1, perPage: 1, blogId: $selectedBlogId, status: 'archived'
        );

        $stats = [
            'published' => (int) $publishedResult['pagination']['total_records'],
            'draft' => (int) $draftResult['pagination']['total_records'],
            'pending' => (int) $pendingResult['pagination']['total_records'],
            'archived' => (int) $archivedResult['pagination']['total_records'],
            'comments' => $this->post->countCommentsByBlogIdAndStatus($selectedBlogId, 'approved'),
            'comments_pending' => $this->post->countCommentsByBlogIdAndStatus($selectedBlogId, 'pending'),
        ];
        $stats['total'] = $stats['published'] + $stats['draft'] + $stats['pending'];

        // "Needs attention" = drafts + pending. We surface them so the creator
        // sees unfinished work the moment they land, before anything else.
        $needsAttention = array_slice(
            array_merge($draftResult['data'], $pendingResult['data']),
            0,
            4
        );

        breadcrumbs()->clear();

        return $this->view([
            'blogIds' => $blogIds,
            'blogSlug' => $blogSlugs[$selectedBlogId],
            'selectedBlogId' => $selectedBlogId,
            'hasNoBlogs' => false,
            'stats' => $stats,
            'recent' => $publishedResult['data'],
            'needsAttention' => $needsAttention,
            'blogsSummary' => count($blogIds) > 1 ? $this->buildBlogsSummary($user['id']) : [],
        ]);
    }

    public function setDefaultBlog()
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $selectedBlogId = (int) $this->request()->all()['blog'];
        $this->preference->setDefaultBlogId($user['id'], $selectedBlogId);

        return $this->redirect('/dashboard');
    }

    /**
     * Per-blog summary cards (only relevant when the user has 2+ blogs).
     *
     * @return array<int, array{id:int,name:string,slug:string,post_count:int}>
     */
    private function buildBlogsSummary(int $userId): array
    {
        $rows = $this->blogModel->getBlogsByOwnerWithCounts($userId);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) ($row['blog_name'] ?? ''),
            'slug' => (string) ($row['blog_slug'] ?? ''),
            'post_count' => (int) ($row['post_count'] ?? 0),
            'status' => (string) ($row['status'] ?? 'draft'),
        ], $rows);
    }

    /** @return array<string,int> */
    private function emptyStats(): array
    {
        return [
            'published' => 0,
            'draft' => 0,
            'pending' => 0,
            'archived' => 0,
            'comments' => 0,
            'comments_pending' => 0,
            'total' => 0,
        ];
    }

    private function getBlog(int $id)
    {
        $blog = $this->blogModel->getBlog((string) $id);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$id}' not found.");
        }

        return $blog;
    }
}
