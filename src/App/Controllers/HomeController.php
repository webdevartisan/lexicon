<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Core\Response;

/**
 * Public front page: hero, stats, admin-curated showcase and FAQ.
 */
class HomeController extends AppController
{
    public function __construct(
        private PostModel $postModel,
        private BlogModel $blogModel,
        private UserModel $userModel
    ) {}

    public function index(): Response
    {
        return $this->view([
            'showcase' => $this->postModel->findHomeShowcase(6),
            'stats' => $this->publicStats(),
        ]);
    }

    /**
     * Platform-wide counts for the stats strip.
     *
     * Cached for an hour because three COUNT queries on every front page hit
     * would defeat the point of a landing page, and the numbers only need to
     * be roughly current.
     *
     * @return array{posts: int, blogs: int, writers: int}
     */
    private function publicStats(): array
    {
        $cached = cache()->get('front:stats');
        if ($cached !== null) {
            $stats = json_decode($cached, true);
            if (is_array($stats)) {
                return $stats;
            }
        }

        $stats = [
            'posts' => $this->postModel->countPublished(),
            'blogs' => $this->blogModel->countPublished(),
            'writers' => $this->userModel->countPublicWriters(),
        ];

        cache()->set('front:stats', (string) json_encode($stats), 3600);

        return $stats;
    }

    public function debugCache(): Response
    {
        $authCheck = auth()->check();
        $user = auth()->user();

        return $this->json([
            'auth_check' => $authCheck,
            'user_id' => $user['id'] ?? null,
            'session_user_id' => $_SESSION['user_id'] ?? null,
            'session_locale' => $_SESSION['locale'] ?? null,
            'all_session' => $_SESSION,
        ]);
    }

    public function csrfToken(): Response
    {
        $this->response->addHeader('Cache-Control', 'no-store, must-revalidate');

        return $this->json(['token' => csrf_token()]);
    }
}
