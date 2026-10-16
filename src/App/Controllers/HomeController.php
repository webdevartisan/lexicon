<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\PublishedPostModel;
use App\Models\UserModel;
use Framework\Core\Response;

/**
 * Public front page: hero, stats, admin-curated showcase and FAQ.
 */
class HomeController extends AppController
{
    private const NEW_TOKENS_PER_MINUTE = 30;

    public function __construct(
        private PublishedPostModel $publishedPosts,
        private BlogModel $blogModel,
        private UserModel $userModel
    ) {}

    public function index(): Response
    {
        return $this->view([
            'showcase' => $this->publishedPosts->findHomeShowcase(6),
            'stats' => $this->publicStats(),
            // Only this page opens on the dark cover, so only this page gets
            // the transparent masthead.
            'navMode' => 'over',
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
            'posts' => $this->publishedPosts->countPublished(),
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

    /**
     * Hand out the session's CSRF token, creating it if needed.
     *
     * Only new tokens are throttled: each one stores a guest session, while
     * reading an existing token costs nothing.
     */
    public function csrfToken(): Response
    {
        $this->response->addHeader('Cache-Control', 'no-store, must-revalidate');

        if (csrf()->peekToken() !== '') {
            return $this->json(['token' => csrf_token()]);
        }

        $key = 'csrf-token:ip:'.($this->request->ip() ?? 'unknown');

        if (rateLimiter()->tooManyAttempts($key, self::NEW_TOKENS_PER_MINUTE, 60)) {
            return $this->jsonError('Too many requests. Please try again in a minute.', 429);
        }

        rateLimiter()->hit($key, 60);

        return $this->json(['token' => csrf_token()]);
    }
}
