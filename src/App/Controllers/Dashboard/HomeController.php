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
 * HomeController manages the dashboard index with client-side tabbed views.
 *
 * handle the empty state when users have no blogs yet and provide
 * clear onboarding for first-time users.
 */
class HomeController extends AppController
{
    public function __construct(
        private PostModel $post,
        private BlogModel $blogModel,
        private UserPreferencesModel $preference
    ) {}

    /**
     * Display dashboard index with paginated posts for each status tab.
     *
     * load separate pagination for published, draft, and archived posts.
     * URL pattern: /dashboard?publishedPage=1&draftPage=2&archivedPage=1
     *
     * @return mixed View response with posts and pagination data
     */
    public function index()
    {
        $user = auth()->user();
        $selectedBlogId = $this->preference->getDefaultBlogId($user['id']) ?? 0;

        // get pagination parameters for each tab from query string
        $publishedPage = max(1, (int) ($this->request->get['publishedPage'] ?? 1));
        $draftPage = max(1, (int) ($this->request->get['draftPage'] ?? 1));
        $pendingPage = max(1, (int) ($this->request->get['pending'] ?? 1));
        $archivedPage = max(1, (int) ($this->request->get['archivedPage'] ?? 1));
        $perPage = 6;

        // get search parameters
        $searchQuery = trim($this->request->get['query'] ?? '');
        $searchStatus = trim($this->request->get['status'] ?? '');
        $isSearch = !empty($searchQuery);

        // get blog metadata for navigation
        $blogs = $this->blogModel->resource($user['id']);

        // check if blogs result is valid (could be false if no blogs exist)
        if (empty($blogs)) {
            // show a helpful message for first-time users
            // $this->flash('info', 'Welcome! Create your first blog to get started.');

            return $this->view([
                'blogIds' => [],
                'blogSlug' => '',
                'posts' => [
                    'published' => [],
                    'draft' => [],
                    'archived' => [],
                ],
                'selectedBlogId' => 0,
                'searchQuery' => '',
                'searchStatus' => '',
                'isSearch' => false,
                'publishedPagination' => $this->getEmptyPagination($perPage),
                'pendingPagination' => $this->getEmptyPagination($perPage),
                'draftPagination' => $this->getEmptyPagination($perPage),
                'archivedPagination' => $this->getEmptyPagination($perPage),
                'hasNoBlogs' => true, // flag this for the view to show onboarding
            ]);
        }

        $blogIds = [];
        $blogSlugs = [];

        foreach ($blogs as $blog) {
            $blogIds[$blog->id()] = $blog->name();
            $blogSlugs[$blog->id()] = $blog->slug();
        }

        // handle case where no valid blog is selected
        if ($selectedBlogId <= 0 || !isset($blogSlugs[$selectedBlogId])) {
            return $this->view([
                'blogIds' => $blogIds,
                'blogSlug' => '',
                'posts' => [
                    'published' => [],
                    'draft' => [],
                    'archived' => [],
                ],
                'selectedBlogId' => $selectedBlogId,
                'searchQuery' => $searchQuery,
                'searchStatus' => $searchStatus,
                'isSearch' => $isSearch,
                'publishedPagination' => $this->getEmptyPagination($perPage),
                'pendingPagination' => $this->getEmptyPagination($perPage),
                'draftPagination' => $this->getEmptyPagination($perPage),
                'archivedPagination' => $this->getEmptyPagination($perPage),
                'hasNoBlogs' => false,
            ]);
        }

        // authorize access to the selected blog
        $blog = $this->getBlog($selectedBlogId);
        Gate::authorize('view', $blog, $user);

        // load paginated posts for each status tab
        $publishedResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'],
            page: $publishedPage,
            perPage: $perPage,
            blogId: $selectedBlogId,
            status: 'published',
            searchQuery: $searchQuery
        );

        $pendingResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'],
            page: $pendingPage,
            perPage: $perPage,
            blogId: $selectedBlogId,
            status: 'pending',
            searchQuery: $searchQuery
        );

        $draftResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'],
            page: $draftPage,
            perPage: $perPage,
            blogId: $selectedBlogId,
            status: 'draft',
            searchQuery: $searchQuery
        );

        $archivedResult = $this->post->findByAuthorWithFiltersPagination(
            authorId: $user['id'],
            page: $archivedPage,
            perPage: $perPage,
            blogId: $selectedBlogId,
            status: 'archived',
            searchQuery: $searchQuery
        );

        // show info message if search returned no results across all tabs
        if ($isSearch && empty($publishedResult['data']) && empty($draftResult['data']) && empty($archivedResult['data'])) {
            $this->flash('info', 'No posts found matching your search.');
        }
        breadcrumbs()->clear();

        return $this->view([
            'blogIds' => $blogIds,
            'blogSlug' => $blogSlugs[$selectedBlogId],
            'posts' => [
                'pending' => $pendingResult['data'],
                'published' => $publishedResult['data'],
                'draft' => $draftResult['data'],
                'archived' => $archivedResult['data'],
            ],
            'selectedBlogId' => $selectedBlogId,
            'searchQuery' => $searchQuery,
            'searchStatus' => $searchStatus,
            'isSearch' => $isSearch,
            'draftPagination' => $draftResult['pagination'],
            'pendingPagination' => $pendingResult['pagination'],
            'publishedPagination' => $publishedResult['pagination'],
            'archivedPagination' => $archivedResult['pagination'],
            'hasNoBlogs' => false,
        ]);
    }

    /**
     * Handle default blog selection change.
     *
     * @return mixed Redirect response
     */
    public function setDefaultBlog()
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $selectedBlogId = $this->request()->all()['blog'];

        $this->preference->setDefaultBlogId($user['id'], $selectedBlogId);

        return $this->redirect('/dashboard');
    }

    /**
     * Generate empty pagination structure.
     *
     * use this when no blog is selected or no data is available.
     *
     * @param  int  $perPage  Posts per page
     * @return array Empty pagination metadata
     */
    private function getEmptyPagination(int $perPage): array
    {
        return [
            'current_page' => 1,
            'per_page' => $perPage,
            'total_records' => 0,
            'total_pages' => 0,
            'has_previous' => false,
            'has_next' => false,
        ];
    }

    /**
     * Retrieve blog by ID or throw exception.
     *
     * @param  int  $id  Blog ID to retrieve
     * @return mixed Blog resource object
     *
     * @throws PageNotFoundException If blog doesn't exist
     */
    private function getBlog(int $id)
    {
        $blog = $this->blogModel->getBlog((string) $id);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$id}' not found.");
        }

        return $blog;
    }
}
