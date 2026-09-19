<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Core\Request;
use Framework\Exceptions\CsrfTokenException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Framework\Security\CsrfMiddleware;
use Framework\Session;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\MiddlewareTestHelper;

/**
 * Images on a post: a featured image that fails must not throw the post away or
 * hide the failure, and media from other sites is refused on save.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = App::container()->get(\Framework\Database::class);
    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    $this->posts = new PostModel($this->db);
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->userId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, []);

    $this->postId = PostFactory::new($this->posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->userId, 'title' => 'Before', 'excerpt' => 'x'])
        ->draft()
        ->create();

    $this->token = App::container()->get(Csrf::class)->getToken();

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public function render(string $template, array $data = []): string
        {
            return '';
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

    $this->save = function (array $extra) {
        $post = array_merge([
            '_token' => $this->token,
            'title' => 'After',
            'content' => '<p>Body</p>',
            'excerpt' => 'x',
            'intent' => 'save_draft',
            'twitter_card_type' => 'summary_large_image',
        ], $extra);

        $request = makeRequest('/dashboard/post/'.$this->postId.'/update', 'POST', $post);
        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, $this->viewer);

        return callController($controller, 'update', $request, (string) $this->postId);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

function flashedErrors(): array
{
    return App::container()->get(Session::class)->get('_flash', [])['error'] ?? [];
}

it('saves the post and returns to the list when there is no image problem', function () {
    $response = ($this->save)([]);

    expect($response->getHeader('Location'))->not->toContain('/edit')
        ->and($this->posts->find($this->postId)['title'])->toBe('After')
        ->and(flashedErrors())->toBeEmpty();
});

it('keeps the writer on the post with a specific error when the upload reference is bad', function () {
    $response = ($this->save)(['uploaded_featured_image_files' => json_encode(['../../../.env'])]);

    expect($response->getHeader('Location'))->toContain('/dashboard/post/'.$this->postId.'/edit')
        ->and($this->posts->find($this->postId)['title'])->toBe('After')
        ->and($this->posts->find($this->postId)['featured_image'])->toBeNull()
        ->and(implode(' ', flashedErrors()))->toContain('featured image could not be added');
});

it('reports an expired temp upload instead of silently dropping it', function () {
    $response = ($this->save)(['uploaded_featured_image_files' => json_encode(['gone-123456789abc.png'])]);

    expect($response->getHeader('Location'))->toContain('/edit')
        ->and(implode(' ', flashedErrors()))->toContain('expired');
});

it('refuses an outside address picked as the featured image', function () {
    $response = ($this->save)(['featured_image_library_url' => 'https://example.com/cat.png']);

    expect($response->getHeader('Location'))->toContain('/edit')
        ->and($this->posts->find($this->postId)['featured_image'])->toBeNull()
        ->and(implode(' ', flashedErrors()))->toContain('outside address');
});

it('rejects a save carrying an invalid CSRF token', function () {
    expect(fn () => ($this->save)(['_token' => 'not-the-token']))->toThrow(CsrfTokenException::class);

    expect($this->posts->find($this->postId)['title'])->toBe('Before');
});

it('refuses an image upload request whose token has expired with a 419 the uploader can read', function () {
    $session = App::container()->get(Session::class);
    $middleware = new CsrfMiddleware(new Csrf($session), $session);

    $request = new Request('/dashboard/upload', 'POST', [], [], [], [], [], [
        'x-csrf-token' => 'expired-token',
        'accept' => 'application/json',
        'x-requested-with' => 'XMLHttpRequest',
    ]);

    $response = $middleware->process($request, MiddlewareTestHelper::createHandler('stored'));

    expect($response->getStatusCode())->toBe(419)
        ->and(json_decode($response->getBody(), true)['success'])->toBeFalse();
});

it('refuses a body that adds an image from another site', function () {
    ($this->save)(['content' => '<p>Hi</p><img src="https://images.example.com/cat.jpg">']);

    $errors = App::container()->get(Session::class)->get('_errors', []);

    expect($this->posts->find($this->postId)['title'])->toBe('Before')
        ->and($errors['content'][0] ?? '')->toContain('images.example.com');
});

it('keeps an outside image the post already had and saves the edit', function () {
    $old = '<p>Old</p><img src="https://images.unsplash.com/photo-1.jpg">';
    $this->posts->update($this->postId, ['content' => $old]);

    ($this->save)(['content' => $old.'<p>Added a line</p>']);

    expect($this->posts->find($this->postId)['content'])->toContain('Added a line')
        ->and($this->posts->find($this->postId)['content'])->toContain('images.unsplash.com');
});

it('saves a post whose excerpt was left empty and clears the old one', function () {
    $response = ($this->save)(['excerpt' => '']);

    expect($response->getHeader('Location'))->not->toContain('/edit')
        ->and(flashedErrors())->toBeEmpty()
        ->and((string) $this->posts->find($this->postId)['excerpt'])->toBe('');
});
