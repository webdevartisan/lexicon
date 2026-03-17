<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\NavigationService;
use Framework\Core\Response;

class HomeController extends AppController
{
    public function __construct(
        private PostModel $postModel,
        private UserModel $userModel,
        private NavigationService $nav
    ) {}

    public function index(): Response
    {
        // Fetch random public posts (limit to 6 for homepage)
        $posts = $this->postModel->findRandomPublicPosts(6);

        // Attach author info to each post
        foreach ($posts as &$post) {
            $author = $this->userModel->findById($post['author_id']);
            $post['author_username'] = $author['username'] ?? 'unknown';
        }

        $items = $this->nav->for('front', $this->request->uri);

        return $this->view([
            'posts' => $posts,
            'nav_items' => $items,
        ]);
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
