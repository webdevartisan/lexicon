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
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * A post's link can change until readers have seen it, through the editor's
 * Save and through autosave, and stays put once the post has been public.
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
    $this->ownerId = UserFactory::new(new UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    expect(auth()->login($email, 'password123'))->toBeTrue();

    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, []);

    $this->post = fn (array $attributes): int => PostFactory::new($this->posts)
        ->withAttributes($attributes + [
            'blog_id' => $this->blogId,
            'author_id' => $this->ownerId,
            'title' => 'Field notes',
            'slug' => 'field-notes',
            'content' => '<p>What we saw on the hill.</p>',
            'status' => 'draft',
            'published_at' => null,
        ])
        ->create();

    $this->save = function (int $postId, string $slug): void {
        $request = makeRequest('/dashboard/post/'.$postId.'/update', 'POST', [
            '_token' => App::container()->get(Csrf::class)->getToken(),
            'twitter_card_type' => 'summary_large_image',
            'title' => 'Field notes',
            'slug' => $slug,
            'content' => '<p>What we saw on the hill.</p>',
            'timezone' => 'UTC',
        ]);
        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());
        callController($controller, 'update', $request, (string) $postId);
    };

    $this->autosave = function (int $postId, string $slug): array {
        $request = makeRequest('/dashboard/post/autosave', 'POST', [
            '_token' => App::container()->get(Csrf::class)->getToken(),
            'id' => $postId,
            'title' => 'Field notes',
            'slug' => $slug,
            'content' => '<p>What we saw on the hill.</p>',
            'timezone' => 'UTC',
        ]);
        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

        return json_decode(callController($controller, 'autosave', $request)->getBody(), true);
    };

    $this->slugOf = fn (int $postId): string => $this->posts->find($postId)['slug'];
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('saves a new link for a draft that was never public', function () {
    $draftId = ($this->post)([]);

    ($this->save)($draftId, 'notes-from-the-hill');

    expect(($this->slugOf)($draftId))->toBe('notes-from-the-hill');
});

it('numbers a draft link another post already uses', function () {
    ($this->post)(['slug' => 'taken-link', 'status' => 'published', 'published_at' => gmdate('Y-m-d H:i:s', time() - 3600)]);
    $draftId = ($this->post)([]);

    ($this->save)($draftId, 'taken-link');

    expect(($this->slugOf)($draftId))->toBe('taken-link-2');
});

it('keeps the link of a published post', function () {
    $liveId = ($this->post)(['status' => 'published', 'published_at' => gmdate('Y-m-d H:i:s', time() - 3600)]);

    ($this->save)($liveId, 'something-else');

    expect(($this->slugOf)($liveId))->toBe('field-notes');
});

it('keeps the link of a post that was public before it went back to draft', function () {
    $pulledBackId = ($this->post)(['published_at' => gmdate('Y-m-d H:i:s', time() - 86400)]);

    ($this->save)($pulledBackId, 'something-else');
    $body = ($this->autosave)($pulledBackId, 'another-thing');

    expect(($this->slugOf)($pulledBackId))->toBe('field-notes')
        ->and($body['slug'])->toBe('field-notes');
});

it('lets autosave keep a draft link and answers with the address it saved', function () {
    ($this->post)(['slug' => 'taken-link', 'status' => 'published', 'published_at' => gmdate('Y-m-d H:i:s', time() - 3600)]);
    $draftId = ($this->post)([]);

    $body = ($this->autosave)($draftId, 'taken-link');

    expect($body['success'])->toBeTrue()
        ->and($body['slug'])->toBe('taken-link-2')
        ->and(($this->slugOf)($draftId))->toBe('taken-link-2');
});
