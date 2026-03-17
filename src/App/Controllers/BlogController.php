<?php

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Exceptions\PageNotFoundException;

class BlogController extends AppController
{
    public function __construct(
        private BlogModel $model,
        private UserModel $userModel,
        private PostModel $postModel,
        private CategoryModel $categoryModel,
        private BlogSettingsModel $settings
    ) {}

    public function index()
    {
        // 1. Core collections for the sidebar / discovery sections
        $blogs = $this->model->getAllBlogsWithOwnerAndCounts();
        $categories = $this->categoryModel->getCategories();
        $featuredCreators = $this->model->getFeaturedCreators();

        // 2. Read filters from query string
        $searchQuery = trim($this->request->get['q'] ?? '');
        $page = (int) ($this->request->get['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        // Optional numeric category filter, e.g. ?category=3
        $categoryId = isset($this->request->get['category'])
            ? (int) $this->request->get['category']
            : null;

        $perPage = 8;

        // 3. Delegate to Post model for the home feed
        if ($searchQuery !== '') {
            // Search within posts and blog names
            $postsData = $this->postModel->searchPublishedPosts(
                $searchQuery,
                $page,
                $perPage,
                $categoryId // <-- uses the existing optional parameter
            );
            $mode = 'search';
        } else {
            // Default recent feed, optionally filtered by category
            $postsData = $this->postModel->getRecentPublishedWithPagination(
                $page,
                $perPage,
                $categoryId
            );
            $mode = 'recent';
        }

        // 4. Build a small DTO-like payload for pagination
        $pagination = [
            'totalPages' => $postsData['totalPages'] ?? 0,
            'currentPage' => $postsData['currentPage'] ?? $page,
            'perPage' => $postsData['perPage'] ?? $perPage,
            'totalPosts' => $postsData['totalPosts'] ?? 0,
        ];

        return $this->view([
            'blogs' => $blogs,
            'categories' => $categories,
            'featuredCreators' => $featuredCreators,
            'posts' => $postsData['data'] ?? [],
            'pagination' => $pagination,
            'searchQuery' => $searchQuery,
            'activeCategory' => $categoryId,
            'mode' => $mode,
        ]);
    }

    public function showBlog(string $blogSlug)
    {
        try {
            $ctx = $this->loadBlogContext($blogSlug);
        } catch (\RuntimeException $e) {
            http_response_code(404);

            return $this->view('errors/404.lex.php');
        }

        $blogId = (int) ($ctx['blog']['id'] ?? 0);
        if ($blogId === 0) {
            http_response_code(404);

            return $this->view('errors/404.lex.php');
        }

        // Determine current page from query param (default 1)
        $page = (int) ($this->request->get['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        // Fetch paginated published posts for this blog
        $postsData = $this->postModel->findPublishedByBlogIdWithPagination($blogId, $page, 5);

        return $this->view('Blogs/show.lex.php', $ctx + [
            'posts' => $postsData['data'],
            'pagination' => [
                'totalPages' => $postsData['totalPages'],
                'currentPage' => $postsData['currentPage'],
                'perPage' => $postsData['perPage'],
                'totalPosts' => $postsData['totalPosts'],
            ],
        ]);
    }

    public function showBlogPost(string $blogSlug, string $postSlug)
    {
        try {
            $ctx = $this->loadBlogContext($blogSlug);
        } catch (\RuntimeException $e) {
            throw new PageNotFoundException('Post not be found', 404);
        }

        // Post
        $post = $this->postModel->findBySlug($postSlug);
        if (!$post) {
           throw new PageNotFoundException('Post not be found', 404);
        }

        if ($post['status'] !== 'published') {
            throw new PageNotFoundException('Post not be found', 404);
        }

        // Guard: post must belong to the resolved author
        if (!empty($post['author_id']) && (int) $post['author_id'] !== (int) $ctx['user']['id']) {
            http_response_code(404);

            return $this->view('errors/404.lex.php');
        }

        // Normalize timestamps
        $rawTs = $post['published_at'] ?? $post['created_at'] ?? gmdate('Y-m-d H:i:s');
        $post['published_at_raw'] = $rawTs;

        // Pretty date with ordinal suffix like "5th Nov 2025"
        $dt = new \DateTime($post['published_at_raw'], new \DateTimeZone('UTC'));
        $post['published_at'] = $this->formatDateWithOrdinal($dt); // see helper below

        // Enrich display fields
        $displayName = empty($ctx['user']['display_name_cached']) ? $ctx['user']['username'] : $ctx['user']['display_name_cached'];
        $post['author_name'] = $displayName;
        $post['cover_url'] = $post['cover_url'] ?? null; // TODO update the key

        // --- comments enabled logic ---
        $blogCommentsEnabled = array_key_exists('comments_enabled', $ctx['settings'])
            ? (bool) $ctx['settings']['comments_enabled']
            : true; // default blog-level on

        $postCommentsEnabled = array_key_exists('comments_enabled', $post)
            ? (bool) $post['comments_enabled']
            : true; // default post-level on

        $commentsEnabled = $blogCommentsEnabled && $postCommentsEnabled;

        // Load comments only if enabled
        $comments = [];
        if ($commentsEnabled && !empty($post['id'])) {
            $comments = $this->postModel->comments((int) $post['id']);
        }

        // Prev/next/related
        $prev_post = $this->postModel->findPreviousByBlogIdAndDate((int) $ctx['blog']['id'], $post['published_at_raw']) ?: null;
        $next_post = $this->postModel->findNextByBlogIdAndDate((int) $ctx['blog']['id'], $post['published_at_raw']) ?: null;
        $related = $this->postModel->findRecentByBlogIdExcludingSlug((int) $ctx['blog']['id'], $postSlug, 4);

        // Merge meta: post > blog > settings defaults
        $meta = [
            'title' => ($post['title'] ?? 'Post').' — '.($ctx['blog']['blog_name'] ?? $ctx['user']['username']."'s Blog"),
            'description' => $post['excerpt'] ?? $ctx['meta']['description'] ?? '',
        ];

        return $this->view('Posts/show.lex.php', $ctx + [
            'flashes' => $this->getFlashMessages(),
            'post' => $post,
            'prev_post' => $prev_post,
            'next_post' => $next_post,
            'related' => $related,
            'meta' => $meta,
            'comments' => $comments,
            'comments_enabled' => $commentsEnabled,
        ]);
    }

    private function loadBlogContext(string $blogSlug): array
    {
        // 1) Blog
        $blog = $this->model->getBlogBySlug($blogSlug);
        if (!$blog) {
            throw new \RuntimeException('Blog not found');
        }

        if (isset($blog['status']) && $blog['status'] !== 'published') {
            throw new \RuntimeException('Blog inactive or missing');
        }

        // 2) Owner
        $user = $this->userModel->findById($blog['owner_id']);

        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        // 3) Settings + meta defaults
        $settings = $this->settings->findByBlogId((int) $blog['id']) ?? [];

        $meta = [
            'title' => $settings['meta_title'] ?? ($blog['blog_name'] ?? ($user['display_name_cached']."'s Blog")),
            'description' => $settings['meta_description'] ?? ($blog['description'] ?? ''),
        ];

        // 4) Back-compat field some templates expect
        $user['blog_name'] = $blog['blog_name'] ?? ($user['username']."'s Blog");

        return compact('user', 'blog', 'settings', 'meta');
    }

    private function formatDateWithOrdinal(\DateTimeInterface $dt, string $tz = 'UTC'): string
    {
        $clone = (new \DateTimeImmutable('@'.$dt->getTimestamp()))->setTimezone(new \DateTimeZone($tz));

        return $clone->format('jS M Y'); // e.g. 5th Nov 2025
    }
}
