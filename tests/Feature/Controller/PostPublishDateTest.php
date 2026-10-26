<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostController;
use App\Helpers\TimezoneHelper;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use Framework\Core\App;
use Framework\Security\Csrf;
use Framework\Session;
use Framework\View\TemplateRenderer;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * The editor shows and reads the publish date on the writer's account clock,
 * so saving never moves it.
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
    $this->preferences = new UserPreferencesModel($this->db);
    $this->preferences->upsert($this->ownerId, ['timezone' => 'America/New_York']);

    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, []);

    // All were last saved from a form drawn on Bucharest time.
    $post = fn (string $status, string $publishedAt): int => PostFactory::new($this->posts)
        ->withAttributes([
            'blog_id' => $this->blogId,
            'author_id' => $this->ownerId,
            'content' => '<p>Ready to go.</p>',
            'status' => $status,
            'published_at' => $publishedAt,
            'timezone' => 'Europe/Bucharest',
        ])
        ->create();
    $this->ids = [
        'scheduled' => $post('scheduled', gmdate('Y-m-d H:i:42', time() + 2 * 86400)),
        'draft' => $post('draft', '2026-10-01 07:15:42'),
        // The second of the two 01:30s in New York that night, the one PHP reads back as the first.
        'repeated' => $post('scheduled', '2027-11-07 06:30:00'),
    ];

    $this->captured = [];
    $test = $this;
    $viewer = Mockery::mock(TemplateRenderer::class)->shouldIgnoreMissing();
    $viewer->shouldReceive('render')->andReturnUsing(function (?string $template, array $data) use ($test): string {
        $test->captured = $data;

        return '';
    });

    $this->controller = function (string $path, string $method, array $fields = []) use ($viewer): PostController {
        $request = makeRequest($path, $method, $fields + ['_token' => App::container()->get(Csrf::class)->getToken()]);
        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, $viewer);

        return $controller;
    };

    // What the date field holds when the edit page opens.
    $this->open = function (int $id): string {
        ($this->controller)('/dashboard/post/'.$id.'/edit', 'GET')->edit((string) $id);

        return (string) $this->captured['post']['published_at'];
    };

    $this->save = function (int $id, string $intent, string $publishedAt, ?string $zone): void {
        ($this->controller)('/dashboard/post/'.$id.'/update', 'POST', [
            'twitter_card_type' => 'summary_large_image',
            'title' => 'Saved again',
            'content' => '<p>Ready to go.</p>',
            'published_at' => $publishedAt,
            'intent' => $intent,
        ] + ($zone === null ? [] : ['timezone' => $zone]))->update((string) $id);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('shows the stored date on the account clock', function () {
    expect(($this->open)($this->ids['draft']))->toBe('2026-10-01 03:15:42')
        ->and($this->captured['publishZone'])->toBe('America/New_York');
});

it('uses the site clock for an account with no zone', function () {
    $this->preferences->upsert($this->ownerId, ['timezone' => '']);

    expect(($this->open)($this->ids['draft']))->toBe(TimezoneHelper::utcToLocal('2026-10-01 07:15:42', site_timezone()))
        ->and($this->captured['publishZone'])->toBe(site_timezone());
});

it('names the account zone in the scheduled line', function () {
    ($this->open)($this->ids['scheduled']);

    expect($this->captured['visibility']['params']['date'])->toEndWith('(America/New York)');
});

it('keeps the moment when the post is saved untouched', function (string $post, string $intent) {
    $id = $this->ids[$post];
    $before = $this->posts->find($id)['published_at'];

    ($this->save)($id, $intent, ($this->open)($id), $this->captured['publishZone']);

    $saved = $this->posts->find($id);
    expect($saved['title'])->toBe('Saved again')
        ->and($saved['published_at'])->toBe($before)
        ->and($saved['timezone'])->toBe('America/New_York');
})->with([
    'a scheduled post' => ['scheduled', 'schedule'],
    'a draft dated in the past' => ['draft', 'save_draft'],
    'a post in the hour the clocks repeat' => ['repeated', 'schedule'],
]);

it('reads the date on the clock the form was drawn with', function () {
    ($this->save)($this->ids['draft'], 'save_draft', '2026-12-01 09:00:00', 'Europe/Bucharest');

    expect($this->posts->find($this->ids['draft'])['published_at'])->toBe('2026-12-01 07:00:00');
});

it('reads the date on the account clock when the form names none', function () {
    ($this->save)($this->ids['draft'], 'save_draft', '2026-12-01 09:00:00', null);

    expect($this->posts->find($this->ids['draft'])['published_at'])->toBe('2026-12-01 14:00:00');
});

it('refuses a date in the old field format and leaves the stored one alone', function () {
    $id = $this->ids['draft'];

    ($this->save)($id, 'save_draft', '20.11.26 10:00', 'America/New_York');

    $saved = $this->posts->find($id);
    expect(App::container()->get(Session::class)->get('_errors', [])['published_at'][0] ?? '')->toBe(TimezoneHelper::INVALID_PUBLISH_DATE)
        ->and($saved['title'])->not->toBe('Saved again')
        ->and($saved['published_at'])->toBe('2026-10-01 07:15:42');
});

it('schedules a new post for the time picked on the account clock', function () {
    $goesLiveAt = time() + 3 * 86400;
    $picked = (new DateTimeImmutable('@'.$goesLiveAt))->setTimezone(new DateTimeZone('America/New_York'));

    ($this->controller)('/dashboard/post/create', 'POST', [
        'twitter_card_type' => 'summary_large_image',
        'title' => 'Coming soon',
        'slug' => 'coming-soon',
        'content' => '<p>Soon.</p>',
        'blog_id' => $this->blogId,
        'timezone' => 'America/New_York',
        'published_at' => $picked->format('Y-m-d H:i:00'),
        'intent' => 'schedule',
    ])->create();

    $saved = $this->posts->findBy('slug', 'coming-soon')[0] ?? [];
    expect($saved['status'] ?? null)->toBe('scheduled')
        ->and($saved['published_at'] ?? null)->toBe(gmdate('Y-m-d H:i:00', $goesLiveAt))
        ->and($saved['timezone'] ?? null)->toBe('America/New_York');
});
