<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\PostModel;
use Framework\Core\Response;

class DiscoverController extends AppController
{
    public function __construct(
        private BlogModel $blogModel,
        private PostModel $postModel,
    ) {}

    public function index(): Response
    {
        $tab = $this->request->get['tab'] ?? 'blogs';
        if (!in_array($tab, ['blogs', 'posts'], true)) {
            $tab = 'blogs';
        }

        $searchQuery = trim($this->request->get['q'] ?? '');
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $data = $tab === 'posts'
            ? $this->postsData($searchQuery, $page)
            : $this->blogsData($searchQuery, $page);

        return $this->view($data + [
            'tab' => $tab,
            'searchQuery' => $searchQuery,
            'featuredCreators' => $this->blogModel->getFeaturedCreators(20),
        ]);
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
            ? $this->postModel->searchPublishedPosts($q, $page, 8)
            : $this->postModel->getRecentPublishedWithPagination($page, 8);

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
