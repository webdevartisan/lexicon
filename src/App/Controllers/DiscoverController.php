<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\PublishedPostModel;
use App\Services\SearchRateLimiter;
use Framework\Core\Response;

class DiscoverController extends AppController
{
    public function __construct(
        private BlogModel $blogModel,
        private PublishedPostModel $publishedPosts,
        private SearchRateLimiter $searchThrottle,
    ) {}

    public function index(): Response
    {
        // Posts lead because Discover is where people find something to read
        $tab = $this->request->get['tab'] ?? 'posts';
        if (!in_array($tab, ['blogs', 'posts'], true)) {
            $tab = 'posts';
        }

        $searchQuery = trim($this->request->get['q'] ?? '');
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $throttled = $tab === 'posts' && $searchQuery !== '' && $this->searchThrottled();

        if ($throttled) {
            $data = ['items' => [], 'pagination' => ['totalPages' => 0, 'currentPage' => 1, 'total' => 0]];
        } else {
            $data = $tab === 'posts'
                ? $this->postsData($searchQuery, $page)
                : $this->blogsData($searchQuery, $page);
        }

        // Each tab opens with its own picks; a search or a later page shows results only
        $showFeatured = $searchQuery === '' && $page === 1;

        return $this->view($data + [
            'tab' => $tab,
            'searchQuery' => $searchQuery,
            'searchThrottled' => $throttled,
            'featuredPosts' => $showFeatured && $tab === 'posts' ? $this->publishedPosts->findPlatformPicks(6) : [],
            'featuredBlogs' => $showFeatured && $tab === 'blogs' ? $this->blogModel->getFeaturedCreators(4) : [],
            'navMode' => 'over',
        ]);
    }

    /**
     * Record a post search from this IP; on refusal set the 429 and Retry-After.
     */
    private function searchThrottled(): bool
    {
        $ip = $this->request->ip() ?? 'unknown';

        if (!$this->searchThrottle->hit($ip)) {
            return false;
        }

        $this->response->setStatusCode(429);
        $this->response->addHeader('Retry-After', (string) max(1, $this->searchThrottle->availableIn($ip)));

        return true;
    }

    /** @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>} */
    private function blogsData(string $q, int $page): array
    {
        $result = $this->blogModel->getDirectoryWithPagination($page, 12, $q);

        return [
            'items' => $result['data'],
            'pagination' => [
                'totalPages' => $result['totalPages'],
                'currentPage' => $result['currentPage'],
                'total' => $result['totalBlogs'],
            ],
        ];
    }

    /** @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>} */
    private function postsData(string $q, int $page): array
    {
        $result = $q !== ''
            ? $this->publishedPosts->searchPublishedPosts($q, $page, 12)
            : $this->publishedPosts->getRecentPublishedWithPagination($page, 12);

        return [
            'items' => $result['data'],
            'pagination' => [
                'totalPages' => $result['totalPages'],
                'currentPage' => $result['currentPage'],
                'total' => $result['totalPosts'],
            ],
        ];
    }
}
