<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\TagModel;
use App\Models\UserModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class BlogController extends AppController
{
    public function __construct(
        private BlogModel $model,
        private UserModel $userModel,
        private PostModel $postModel,
        private CategoryModel $categoryModel,
        private BlogSettingsModel $settings,
        private TagModel $tagModel
    ) {}

    public function index()
    {
        // 1. Core collections for the sidebar / discovery sections
        $blogs = $this->model->getAllBlogsWithOwnerAndCounts();
        $categories = $this->categoryModel->getPublishedTopics();
        $featuredCreators = $this->model->getFeaturedCreators();

        // 2. Read filters from query string
        $searchQuery = trim($this->request->get['q'] ?? '');
        $page = (int) ($this->request->get['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        // Optional topic filter by category name, e.g. ?category=Guides
        $categoryName = trim((string) ($this->request->get['category'] ?? ''));
        if ($categoryName === '') {
            $categoryName = null;
        }

        $perPage = 8;

        // 3. Delegate to Post model for the home feed
        if ($searchQuery !== '') {
            // Search within posts and blog names
            $postsData = $this->postModel->searchPublishedPosts(
                $searchQuery,
                $page,
                $perPage,
                $categoryName
            );
            $mode = 'search';
        } else {
            // Default recent feed, optionally filtered by category
            $postsData = $this->postModel->getRecentPublishedWithPagination(
                $page,
                $perPage,
                $categoryName
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
            'activeCategory' => $categoryName,
            'mode' => $mode,
        ]);
    }

    public function showBlog(string $blogSlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);
        if ($blogId === 0) {
            throw new PageNotFoundException('Blog not found.');
        }

        $featured = $this->postModel->findFeaturedByBlogId($blogId);
        $landingData = $this->postModel->findPublishedByBlogIdWithPagination($blogId, 1, 7);
        $posts = $landingData['data'];

        // Put the chosen headline first without duplicating it in the grid below.
        if ($featured !== null) {
            $posts = array_values(array_filter(
                $posts,
                static fn (array $p): bool => (int) $p['id'] !== (int) $featured['id']
            ));
            array_unshift($posts, $featured);
        }

        return $this->view('Blogs/show.lex.php', $ctx + [
            'posts' => $this->enrichCardPosts($posts),
            'categories' => $this->categoryModel->getPublishedByBlogId($blogId),
            'totalPosts' => (int) ($landingData['totalPosts'] ?? 0),
        ]);
    }

    /**
     * AJAX: the landing's card grid for a category (or recent for "All").
     *
     * Returns just the cards as an HTML fragment, rendered through the active
     * theme so each theme keeps its own card markup. The headline post is
     * excluded — same rule as showBlog — so it never doubles in the grid.
     */
    public function indexFeed(string $blogSlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);
        if ($blogId === 0) {
            throw new PageNotFoundException('Blog not found.', 404);
        }

        // Headline = featured post, else newest — mirror showBlog so "All" matches.
        $featured = $this->postModel->findFeaturedByBlogId($blogId);
        if ($featured !== null) {
            $headlineId = (int) $featured['id'];
        } else {
            $newest = $this->postModel->findPublishedByBlogAndCategory($blogId, null, 1);
            $headlineId = isset($newest[0]['id']) ? (int) $newest[0]['id'] : null;
        }

        $categoryId = null;
        $slug = trim((string) ($this->request->get['category'] ?? ''));
        if ($slug !== '') {
            $category = $this->categoryModel->findBySlugInBlog($blogId, $slug);
            if (!$category) {
                throw new PageNotFoundException('Category not found.', 404);
            }
            $categoryId = (int) $category['id'];
        }

        $cards = $this->postModel->findPublishedByBlogAndCategory($blogId, $categoryId, 6, $headlineId);

        return $this->view('Blogs/_index_cards.lex.php', [
            'cards' => $this->enrichCardPosts($cards),
            'blog' => $ctx['blog'],
            'user' => $ctx['user'],
        ]);
    }

    /**
     * Attach display taxonomy (category name + slug, tag name/slug pairs) to a
     * list of post rows, so the card markup can render it. Shared by the landing
     * and the AJAX feed.
     */
    private function enrichCardPosts(array $posts): array
    {
        foreach ($posts as &$post) {
            $category = !empty($post['category_id'])
                ? $this->categoryModel->findById((int) $post['category_id'])
                : null;
            $post['category'] = $category['name'] ?? null;
            $post['category_slug'] = $category['slug'] ?? null;

            $post['tags'] = array_map(
                static fn (array $t): array => ['name' => $t['name'], 'slug' => $t['slug']],
                $this->postModel->tags((int) $post['id'])
            );
        }
        unset($post);

        return $posts;
    }

    public function archiveBlog(string $blogSlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);

        if ($blogId === 0) {
            throw new PageNotFoundException('Blog not found.', 404);
        }

        $page = max(1, (int) ($this->request->get['page'] ?? 1));
        $postsData = $this->postModel->findPublishedByBlogIdWithPagination($blogId, $page, 12);

        return $this->view('Blogs/archive.lex.php', $ctx + [
            'posts' => $postsData['data'],
            'pagination' => [
                'totalPages' => $postsData['totalPages'],
                'currentPage' => $postsData['currentPage'],
                'perPage' => $postsData['perPage'],
                'totalPosts' => $postsData['totalPosts'],
            ],
        ]);
    }

    /**
     * Published posts in a blog filed under one category.
     */
    public function showCategory(string $blogSlug, string $categorySlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);

        $category = $this->categoryModel->findBySlugInBlog($blogId, $categorySlug);
        if (!$category) {
            throw new PageNotFoundException('Category not found.', 404);
        }

        $posts = $this->categoryModel->posts((int) $category['id']);
        $name = e($category['name']);

        return $this->view('Blogs/archive.lex.php', $ctx + [
            'posts' => $posts,
            'pagination' => $this->taxonomyPagination(count($posts)),
            'archiveKicker' => 'Category &mdash; '.$name,
            'archiveTitle' => $name,
            'archiveDek' => 'Posts filed under <em>'.$name.'</em>.',
        ]);
    }

    /**
     * Published posts in a blog carrying one tag.
     */
    public function showTag(string $blogSlug, string $tagSlug): Response
    {
        $ctx = $this->loadBlogContext($blogSlug);
        $blogId = (int) ($ctx['blog']['id'] ?? 0);

        $tag = $this->tagModel->findBySlugInBlog($blogId, $tagSlug);
        if (!$tag) {
            throw new PageNotFoundException('Tag not found.', 404);
        }

        $posts = $this->tagModel->posts((int) $tag['id']);
        $name = e($tag['name']);

        return $this->view('Blogs/archive.lex.php', $ctx + [
            'posts' => $posts,
            'pagination' => $this->taxonomyPagination(count($posts)),
            'archiveKicker' => 'Tagged &mdash; '.$name,
            'archiveTitle' => '#'.$name,
            'archiveDek' => 'Posts tagged <em>'.$name.'</em>.',
        ]);
    }

    /**
     * Single-page pagination shape for taxonomy listings (no paging for now).
     *
     * @return array{totalPages:int,currentPage:int,perPage:int,totalPosts:int}
     */
    private function taxonomyPagination(int $count): array
    {
        return [
            'totalPages' => 1,
            'currentPage' => 1,
            'perPage' => max(1, $count),
            'totalPosts' => $count,
        ];
    }

    public function showBlogPost(string $blogSlug, string $postSlug)
    {
        $ctx = $this->loadBlogContext($blogSlug);

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

        // Taxonomy for display: category name + slug, and tags as name/slug pairs.
        $category = !empty($post['category_id'])
            ? $this->categoryModel->findById((int) $post['category_id'])
            : null;
        $post['category'] = $category['name'] ?? null;
        $post['category_slug'] = $category['slug'] ?? null;

        $post['tags'] = array_map(
            static fn (array $t): array => ['name' => $t['name'], 'slug' => $t['slug']],
            $this->postModel->tags((int) $post['id'])
        );

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
            throw new PageNotFoundException('Blog not found', 404);
        }

        if (isset($blog['status']) && $blog['status'] !== 'published') {
            throw new PageNotFoundException('Blog not found', 404);
        }

        // 2) Owner
        $user = $this->userModel->findById($blog['owner_id']);

        if (!$user) {
            throw new PageNotFoundException('Blog not found', 404);
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
