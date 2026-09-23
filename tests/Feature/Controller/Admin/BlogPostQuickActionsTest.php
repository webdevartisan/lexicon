<?php

declare(strict_types=1);

use App\Controllers\Admin\BlogController;
use App\Controllers\Admin\PostController;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\BlogDeletionService;
use App\Services\BlogOwnershipService;
use App\Services\ExternalMediaGuard;
use App\Services\MediaService;
use App\Services\PostContentSanitizer;
use App\Services\PublicCacheInvalidator;
use App\Services\ThemeService;
use Framework\Database;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Feature tests for the admin quick-action rows added to /admin/blogs and
 * /admin/posts: status and visibility changes triggered straight from the
 * row-actions dropdown rather than the full edit form.
 *
 * The two guards these protect are both existing, documented invariants:
 * a suspended blog's status is owned by the suspension cascade, and a
 * moderated post's status is owned by its report case.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }

    $this->db = \Framework\Core\App::container()->get(Database::class);

    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->postModel = new PostModel($this->db);

    $password = 'password123';
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)])
        ->create();

    expect(auth()->login($email, $password))->toBeTrue();

    $container = \Framework\Core\App::container();

    $this->blogController = new BlogController(
        $this->blogModel,
        $container->get(PublicCacheInvalidator::class),
        $container->get(ThemeService::class),
        $container->get(BlogOwnershipService::class),
        $container->get(BlogDeletionService::class),
    );

    $this->postController = new PostController(
        $this->postModel,
        $this->blogModel,
        $this->db,
        $container->get(PublicCacheInvalidator::class),
        $container->get(ExternalMediaGuard::class),
        $container->get(PostContentSanitizer::class),
        $container->get(MediaService::class),
    );

    $this->mockViewer = new class() implements TemplateViewerInterface
    {
        public function render(string $template, array $data = []): string
        {
            return 'mocked view';
        }

        public function addGlobals(array $vars): void {}

        public function compiledViewStats(): array
        {
            return [];
        }

        public function pruneCompiledViews(int $maxAgeSeconds): int
        {
            return 0;
        }

        public function clearCompiledViews(): array
        {
            return [];
        }
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

/** Run a request through a controller with a real, session-matched CSRF token. */
function withCsrf($controller, TemplateViewerInterface $viewer, string $uri): void
{
    $request = makeRequest($uri, 'POST', ['_token' => csrf()->getToken()]);
    setupController($controller, $request, $viewer);
}

// ============================================================================
// BlogController::publish() / unpublish()
// ============================================================================

it('publishes a draft blog', function () {
    $blogId = BlogFactory::new($this->blogModel)->withAttributes(['status' => 'draft'])->create($this->userId);

    withCsrf($this->blogController, $this->mockViewer, "/admin/blogs/{$blogId}/publish");
    $response = $this->blogController->publish((string) $blogId);

    expect($response->getStatusCode())->toBe(302)
        ->and($this->blogModel->getBlog($blogId)->toArray()['status'])->toBe('published');
});

it('unpublishes a published blog', function () {
    $blogId = BlogFactory::new($this->blogModel)->published()->create($this->userId);

    withCsrf($this->blogController, $this->mockViewer, "/admin/blogs/{$blogId}/unpublish");
    $response = $this->blogController->unpublish((string) $blogId);

    expect($response->getStatusCode())->toBe(302)
        ->and($this->blogModel->getBlog($blogId)->toArray()['status'])->toBe('draft');
});

it('refuses to publish a suspended blog', function () {
    $blogId = BlogFactory::new($this->blogModel)->withAttributes(['status' => BlogModel::STATUS_SUSPENDED])->create($this->userId);

    withCsrf($this->blogController, $this->mockViewer, "/admin/blogs/{$blogId}/publish");
    $this->blogController->publish((string) $blogId);

    expect($this->blogModel->getBlog($blogId)->toArray()['status'])->toBe(BlogModel::STATUS_SUSPENDED);
});

it('refuses to unpublish a suspended blog', function () {
    $blogId = BlogFactory::new($this->blogModel)->withAttributes(['status' => BlogModel::STATUS_SUSPENDED])->create($this->userId);

    withCsrf($this->blogController, $this->mockViewer, "/admin/blogs/{$blogId}/unpublish");
    $this->blogController->unpublish((string) $blogId);

    expect($this->blogModel->getBlog($blogId)->toArray()['status'])->toBe(BlogModel::STATUS_SUSPENDED);
});

// ============================================================================
// PostController status quick actions
// ============================================================================

it('moves a published post to draft, then archives it, then republishes it', function () {
    $blogId = BlogFactory::new($this->blogModel)->published()->create($this->userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $this->userId, 'blog_id' => $blogId, 'status' => 'published'])
        ->create();

    withCsrf($this->postController, $this->mockViewer, "/admin/posts/{$postId}/draft");
    $this->postController->draft((string) $postId);
    expect($this->postModel->find($postId)['status'])->toBe('draft');

    withCsrf($this->postController, $this->mockViewer, "/admin/posts/{$postId}/archive");
    $this->postController->archive((string) $postId);
    expect($this->postModel->find($postId)['status'])->toBe('archived');

    withCsrf($this->postController, $this->mockViewer, "/admin/posts/{$postId}/publish");
    $response = $this->postController->publish((string) $postId);
    expect($response->getStatusCode())->toBe(302)
        ->and($this->postModel->find($postId)['status'])->toBe('published');
});

it('refuses every quick status action on a moderated post', function () {
    $blogId = BlogFactory::new($this->blogModel)->published()->create($this->userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $this->userId, 'blog_id' => $blogId, 'status' => 'moderated'])
        ->create();

    foreach (['publish', 'draft', 'archive'] as $action) {
        withCsrf($this->postController, $this->mockViewer, "/admin/posts/{$postId}/{$action}");
        $this->postController->{$action}((string) $postId);
    }

    expect($this->postModel->find($postId)['status'])->toBe('moderated');
});

// ============================================================================
// PostController visibility quick actions
// ============================================================================

it('cycles a post through private, unlisted, and public', function () {
    $blogId = BlogFactory::new($this->blogModel)->published()->create($this->userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $this->userId, 'blog_id' => $blogId, 'status' => 'published', 'visibility' => 'public'])
        ->create();

    withCsrf($this->postController, $this->mockViewer, "/admin/posts/{$postId}/make-private");
    $this->postController->makePrivate((string) $postId);
    expect($this->postModel->find($postId)['visibility'])->toBe('private');

    withCsrf($this->postController, $this->mockViewer, "/admin/posts/{$postId}/unlist");
    $this->postController->unlist((string) $postId);
    expect($this->postModel->find($postId)['visibility'])->toBe('unlisted');

    withCsrf($this->postController, $this->mockViewer, "/admin/posts/{$postId}/make-public");
    $response = $this->postController->makePublic((string) $postId);
    expect($response->getStatusCode())->toBe(302)
        ->and($this->postModel->find($postId)['visibility'])->toBe('public');
});

it('allows a visibility change on a moderated post without lifting its status', function () {
    $blogId = BlogFactory::new($this->blogModel)->published()->create($this->userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $this->userId, 'blog_id' => $blogId, 'status' => 'moderated', 'visibility' => 'public'])
        ->create();

    withCsrf($this->postController, $this->mockViewer, "/admin/posts/{$postId}/make-private");
    $this->postController->makePrivate((string) $postId);

    $post = $this->postModel->find($postId);
    expect($post['visibility'])->toBe('private')
        ->and($post['status'])->toBe('moderated');
});
