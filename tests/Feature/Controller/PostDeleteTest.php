<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Framework\Session;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Deleting a post says which post went and goes back to the post list the
 * writer came from, never further than the post list.
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
    $ownerId = UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($blogId, []);

    $this->postId = PostFactory::new($this->posts)
        ->withAttributes(['blog_id' => $blogId, 'author_id' => $ownerId, 'title' => 'Notes from the field'])
        ->draft()
        ->create();

    $this->session = App::container()->get(Session::class);

    $this->wayBack = function (string $url): string {
        $this->session->set('return_urls', ['abc123' => ['url' => $url, 'expires_at' => time() + 600]]);

        return 'abc123';
    };

    $this->destroy = function (array $query = []) {
        $request = makeRequest('/dashboard/post/'.$this->postId.'/destroy', 'POST', [
            '_token' => App::container()->get(Csrf::class)->getToken(),
        ], $query);
        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

        return callController($controller, 'destroy', $request, (string) $this->postId);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('goes back to the post list it was opened from', function () {
    $response = ($this->destroy)(['r' => ($this->wayBack)('/dashboard/post?status=draft&page=2')]);

    expect($this->posts->find($this->postId))->toBeFalsy()
        ->and($response->getHeader('Location'))->toEndWith('/dashboard/post?status=draft&page=2');
});

it('goes to the post list when it has no way back', function () {
    $response = ($this->destroy)();

    expect(parse_url((string) $response->getHeader('Location'), PHP_URL_PATH))->toEndWith('/dashboard/post');
});

it('goes no further than the post list, whatever the way back says', function () {
    $response = ($this->destroy)(['r' => ($this->wayBack)('/dashboard/settings')]);

    expect(parse_url((string) $response->getHeader('Location'), PHP_URL_PATH))->toEndWith('/dashboard/post');
});

it('names the post it deleted', function () {
    ($this->destroy)();

    expect(implode(' ', $this->session->get('_flash', [])['success'] ?? []))->toContain('Notes from the field');
});
