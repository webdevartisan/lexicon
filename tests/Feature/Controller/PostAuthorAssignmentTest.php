<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\PostAuthorService;
use Framework\Core\App;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Security\Csrf;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Who a post is credited to: only the blog's writers can be picked, and only
 * owners and editors can pick.
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
    $this->blogs = new BlogModel($this->db);
    $users = new UserModel($this->db);

    $this->makeUser = function () use ($users): array {
        $email = faker()->unique()->safeEmail();
        $id = UserFactory::new($users)
            ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
            ->create();

        return [$id, $email];
    };

    [$this->ownerId, $this->ownerEmail] = ($this->makeUser)();
    [$this->editorId, $this->editorEmail] = ($this->makeUser)();
    [$this->writerId, $this->writerEmail] = ($this->makeUser)();
    [$this->reviewerId] = ($this->makeUser)();
    [$this->outsiderId] = ($this->makeUser)();

    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, []);
    $this->blogs->addUserToBlog($this->blogId, $this->editorId, 'editor', $this->ownerId);
    $this->blogs->addUserToBlog($this->blogId, $this->writerId, 'author', $this->ownerId);
    $this->blogs->addUserToBlog($this->blogId, $this->reviewerId, 'reviewer', $this->ownerId);

    $this->postId = PostFactory::new($this->posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->writerId, 'title' => 'Draft', 'excerpt' => 'x'])
        ->draft()
        ->create();

    $viewer = new class() implements TemplateViewerInterface
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

    $this->saveAs = function (string $email, array $extra) use ($viewer) {
        auth()->logout();
        expect(auth()->login($email, 'password123'))->toBeTrue();

        $request = makeRequest('/dashboard/post/'.$this->postId.'/update', 'POST', array_merge([
            '_token' => App::container()->get(Csrf::class)->getToken(),
            'title' => 'Draft',
            'content' => '<p>Body</p>',
            'excerpt' => 'x',
            'intent' => 'save_draft',
            'twitter_card_type' => 'summary_large_image',
        ], $extra));

        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, $viewer);

        return callController($controller, 'update', $request, (string) $this->postId);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('lists only the owner and team members who can write posts', function () {
    $blog = $this->blogs->getBlog($this->blogId);
    $candidates = (new PostAuthorService($this->blogs))->candidates($blog);

    expect(array_keys($candidates))->toEqualCanonicalizing([$this->ownerId, $this->editorId, $this->writerId])
        ->and($candidates)->not->toHaveKey($this->reviewerId)
        ->and($candidates)->not->toHaveKey($this->outsiderId);
});

it('drops a collaborator from the list once they are removed from the team', function () {
    $this->blogs->revokeUserFromBlog($this->blogId, $this->writerId);

    $candidates = (new PostAuthorService($this->blogs))->candidates($this->blogs->getBlog($this->blogId));

    expect($candidates)->not->toHaveKey($this->writerId);
});

it('lets an editor credit the post to another writer', function () {
    ($this->saveAs)($this->editorEmail, ['author_id' => (string) $this->ownerId]);

    expect((int) $this->posts->find($this->postId)['author_id'])->toBe($this->ownerId);
});

it('lets the owner credit the post to a team writer', function () {
    ($this->saveAs)($this->ownerEmail, ['author_id' => (string) $this->editorId]);

    expect((int) $this->posts->find($this->postId)['author_id'])->toBe($this->editorId);
});

it('refuses an author trying to reassign their post by forging the field', function () {
    expect(fn () => ($this->saveAs)($this->writerEmail, ['author_id' => (string) $this->ownerId]))
        ->toThrow(UnauthorizedException::class);

    expect((int) $this->posts->find($this->postId)['author_id'])->toBe($this->writerId);
});

it('lets an author save when the author field is absent or unchanged', function () {
    ($this->saveAs)($this->writerEmail, []);
    ($this->saveAs)($this->writerEmail, ['author_id' => (string) $this->writerId]);

    expect((int) $this->posts->find($this->postId)['author_id'])->toBe($this->writerId);
});

it('rejects crediting someone outside the team with a field error', function (string $who) {
    $target = $who === 'outsider' ? $this->outsiderId : $this->reviewerId;

    ($this->saveAs)($this->ownerEmail, ['author_id' => (string) $target]);

    $errors = App::container()->get(\Framework\Session::class)->get('_errors', []);

    expect((int) $this->posts->find($this->postId)['author_id'])->toBe($this->writerId)
        ->and($errors['author_id'] ?? [])->not->toBeEmpty();
})->with(['outsider', 'reviewer']);
