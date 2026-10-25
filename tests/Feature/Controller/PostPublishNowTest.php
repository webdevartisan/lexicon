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
 * "Publish now" on a scheduled post puts it live straight away, dated now,
 * while publishing with a date still ahead keeps scheduling it.
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

    $this->goesLiveAt = time() + 2 * 86400;
    $this->scheduledId = PostFactory::new($this->posts)
        ->withAttributes([
            'blog_id' => $blogId,
            'author_id' => $ownerId,
            'content' => '<p>Ready to go.</p>',
            'status' => 'scheduled',
            'published_at' => gmdate('Y-m-d H:i:s', $this->goesLiveAt),
        ])
        ->create();

    // Sends the editor form with its date field showing the scheduled time, unless told otherwise.
    $this->press = function (string $intent, ?int $dateShown = null): void {
        $request = makeRequest('/dashboard/post/'.$this->scheduledId.'/update', 'POST', [
            '_token' => App::container()->get(Csrf::class)->getToken(),
            'twitter_card_type' => 'summary_large_image',
            'title' => 'Going out',
            'content' => '<p>Ready to go.</p>',
            'timezone' => 'UTC',
            'published_at' => gmdate('d.m.y H:i', $dateShown ?? $this->goesLiveAt),
            'intent' => $intent,
        ]);
        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());
        callController($controller, 'update', $request, (string) $this->scheduledId);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('publishes a scheduled post now and dates it now', function () {
    ($this->press)('publish_now');

    $saved = $this->posts->find($this->scheduledId);
    $publishedAt = strtotime($saved['published_at'].' UTC');

    expect($saved['status'])->toBe('published')
        ->and($publishedAt)->toBeLessThanOrEqual(time())
        ->and($publishedAt)->toBeGreaterThan(time() - 60);
});

it('dates the post now even when another time ahead was picked before publishing now', function () {
    ($this->press)('publish_now', $this->goesLiveAt + 86400);

    $saved = $this->posts->find($this->scheduledId);

    expect($saved['status'])->toBe('published')
        ->and(strtotime($saved['published_at'].' UTC'))->toBeLessThanOrEqual(time());
});

it('keeps scheduling when publishing with the date still ahead', function () {
    ($this->press)('publish');

    $saved = $this->posts->find($this->scheduledId);

    expect($saved['status'])->toBe('scheduled')
        ->and(strtotime($saved['published_at'].' UTC'))->toBe(intdiv($this->goesLiveAt, 60) * 60);
});
