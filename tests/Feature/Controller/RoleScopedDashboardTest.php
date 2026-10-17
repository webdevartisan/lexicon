<?php

declare(strict_types=1);

use App\Controllers\Dashboard\PostController;
use App\Controllers\Dashboard\PostStatusController;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\NavigationService;
use App\Services\OverviewService;
use Framework\Core\App;
use Framework\Exceptions\UnauthorizedException;
use Framework\Security\Csrf;
use Framework\View\TemplateRenderer;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * With a shared blog active, each role sees and reaches only what its
 * permissions allow: the Posts list, the Overview sections, the sidebar, and
 * the post actions behind them. A custom role is treated by its permissions.
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

    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->posts = new PostModel($this->db);
    $this->preferences = new UserPreferencesModel($this->db);

    $this->ownerId = UserFactory::new($this->users)->create();
    $this->otherWriterId = UserFactory::new($this->users)->create();
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
    (new BlogSettingsModel($this->db))->createDefaultForBlog($this->blogId, []);
    $this->db->query('UPDATE blog_settings SET workflow_enabled = 1 WHERE blog_id = ?', [$this->blogId]);
    $this->blogs->addUserToBlog($this->blogId, $this->otherWriterId, 'author', $this->ownerId);

    $this->othersPostId = PostFactory::new($this->posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->otherWriterId, 'status' => 'draft'])
        ->create();

    // Joins the blog with $role, makes it active and signs in. Returns the member id.
    $this->signInAs = function (string $role): int {
        $email = faker()->unique()->safeEmail();
        $memberId = UserFactory::new($this->users)
            ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
            ->create();
        if ($role === 'owner') {
            $this->db->query('UPDATE blogs SET owner_id = ? WHERE id = ?', [$memberId, $this->blogId]);
        } else {
            $this->blogs->addUserToBlog($this->blogId, $memberId, $role, $this->ownerId);
        }
        $this->preferences->setDefaultBlogId($memberId, $this->blogId);
        expect(auth()->login($email, 'password123'))->toBeTrue();

        return $memberId;
    };

    $this->ownPost = fn (int $authorId): int => PostFactory::new($this->posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $authorId, 'status' => 'draft'])
        ->create();

    $this->captured = [];
    $test = $this;
    $this->viewer = Mockery::mock(TemplateRenderer::class)->shouldIgnoreMissing();
    $this->viewer->shouldReceive('render')->andReturnUsing(function (?string $template, array $data) use ($test): string {
        $test->captured = $data;

        return '';
    });

    $this->postController = function (string $path, string $method = 'GET', array $post = []): PostController {
        $request = makeRequest($path, $method, $post + ['_token' => App::container()->get(Csrf::class)->getToken()]);
        $controller = App::container()->get(PostController::class);
        setupController($controller, $request, $this->viewer);

        return $controller;
    };

    $this->statusController = function (string $path): PostStatusController {
        $request = makeRequest($path, 'POST', ['_token' => App::container()->get(Csrf::class)->getToken()]);
        $controller = App::container()->get(PostStatusController::class);
        setupController($controller, $request, $this->viewer);

        return $controller;
    };

    $this->sidebar = function (): array {
        $blog = $this->blogs->getBlog($this->blogId);
        $nav = new NavigationService(require ROOT_PATH.'/config/navigation.php', auth());
        $items = $nav->for('back', '/dashboard', $blog, ['isReader' => false, 'isCreator' => true, 'workflowEnabled' => true]);

        return array_values(array_filter(array_map(
            static fn (array $item): ?string => $item['type'] === 'link' ? $item['key'] : null,
            $items
        )));
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

it('lists every post in the active blog for roles that manage posts', function (string $role) {
    $memberId = ($this->signInAs)($role);
    ($this->ownPost)($memberId);

    ($this->postController)('/dashboard/post')->index();

    expect($this->captured['managesAll'])->toBeTrue()
        ->and(array_column($this->captured['posts'], 'id'))->toContain($this->othersPostId)
        ->and($this->captured['counts']['draft'])->toBe(2);
})->with(['owner', 'editor']);

it('lists only their own posts for writers', function (string $role) {
    $memberId = ($this->signInAs)($role);
    $mine = ($this->ownPost)($memberId);

    ($this->postController)('/dashboard/post')->index();

    expect($this->captured['managesAll'])->toBeFalse()
        ->and(array_column($this->captured['posts'], 'id'))->toBe([$mine])
        ->and($this->captured['counts']['all'])->toBe(1);
})->with(['author', 'contributor']);

it('refuses the Posts page to a reviewer', function () {
    ($this->signInAs)('reviewer');

    expect(fn () => ($this->postController)('/dashboard/post')->index())->toThrow(UnauthorizedException::class);
});

it('refuses another writer\'s post to an author, by URL and by autosave', function () {
    ($this->signInAs)('author');

    expect(fn () => ($this->postController)('/dashboard/post/'.$this->othersPostId.'/edit')->edit((string) $this->othersPostId))
        ->toThrow(UnauthorizedException::class);

    $response = ($this->postController)('/dashboard/post/autosave', 'POST', [
        'id' => (string) $this->othersPostId, 'title' => 'Taken over', 'slug' => 'taken-over', 'content' => '<p>x</p>', 'timezone' => 'UTC',
    ])->autosave();

    expect($response->getStatusCode())->toBe(403)
        ->and($this->posts->find($this->othersPostId)['title'])->not->toBe('Taken over');
});

it('refuses publishing to a contributor', function () {
    $memberId = ($this->signInAs)('contributor');
    $mine = ($this->ownPost)($memberId);

    expect(fn () => ($this->statusController)('/dashboard/post/'.$mine.'/publish')->publish((string) $mine))
        ->toThrow(UnauthorizedException::class);
});

it('refuses post creation to a reviewer', function () {
    ($this->signInAs)('reviewer');

    expect(fn () => ($this->postController)('/dashboard/post/new')->new())->toThrow(UnauthorizedException::class);
});

it('shows each role only its Overview sections', function (string $role, array $present, array $absent) {
    ($this->signInAs)($role);

    $overview = App::container()->get(OverviewService::class)->for($this->blogs->getBlog($this->blogId), auth()->user());

    foreach ($present as $section) {
        expect($overview[$section])->not->toBeNull("{$role} should see {$section}");
    }
    foreach ($absent as $section) {
        expect($overview[$section])->toBeNull("{$role} should not see {$section}");
    }
})->with([
    'owner' => ['owner', ['blogPosts', 'comments', 'subscribers', 'review'], ['myWork']],
    'editor' => ['editor', ['blogPosts', 'comments', 'review'], ['myWork', 'subscribers']],
    'author' => ['author', ['myWork'], ['blogPosts', 'comments', 'subscribers', 'review']],
    'contributor' => ['contributor', ['myWork'], ['blogPosts', 'comments', 'subscribers', 'review']],
    'reviewer' => ['reviewer', ['review'], ['blogPosts', 'myWork', 'comments', 'subscribers']],
]);

it('shows each role only the sidebar items it may use', function (string $role, array $expected) {
    ($this->signInAs)($role);

    expect(array_values(array_filter(($this->sidebar)(), static fn (string $key): bool => !str_starts_with($key, 'navigation.insightsPages'))))
        ->toBe($expected);
})->with([
    'owner' => ['owner', ['navigation.overview', 'navigation.blogs', 'navigation.posts', 'navigation.reviewQueue', 'navigation.comments', 'navigation.categoriesTags', 'navigation.mediaLibrary', 'navigation.editBlogSettings', 'navigation.team', 'navigation.subscribers', 'navigation.backup', 'navigation.appearanceTheme', 'navigation.insights']],
    'editor' => ['editor', ['navigation.overview', 'navigation.blogs', 'navigation.posts', 'navigation.reviewQueue', 'navigation.comments', 'navigation.categoriesTags', 'navigation.mediaLibrary', 'navigation.editBlogSettings', 'navigation.appearanceTheme', 'navigation.insights']],
    'author' => ['author', ['navigation.overview', 'navigation.blogs', 'navigation.posts', 'navigation.mediaLibrary', 'navigation.insights']],
    'contributor' => ['contributor', ['navigation.overview', 'navigation.blogs', 'navigation.posts', 'navigation.mediaLibrary', 'navigation.insights']],
    'reviewer' => ['reviewer', ['navigation.overview', 'navigation.blogs', 'navigation.reviewQueue']],
]);

it('treats a custom role by its permissions, not its name', function () {
    $slug = 'desk_'.bin2hex(random_bytes(4));
    $this->db->query("INSERT INTO roles (role_name, role_slug, description, scope, is_system, level) VALUES ('Desk', ?, '', 'blog', 0, 45)", [$slug]);
    $this->db->query(
        "INSERT INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_slug IN ('view_all_posts', 'review_posts', 'approve_posts')
         WHERE r.role_slug = ?",
        [$slug]
    );
    ($this->signInAs)($slug);

    $overview = App::container()->get(OverviewService::class)->for($this->blogs->getBlog($this->blogId), auth()->user());

    expect($overview['review'])->not->toBeNull()
        ->and($overview['blogPosts'])->toBeNull()
        ->and($overview['canCreate'])->toBeFalse()
        ->and(($this->sidebar)())->toBe(['navigation.overview', 'navigation.blogs', 'navigation.reviewQueue']);
});

it('refuses the media library to a reviewer and opens it to a writer', function () {
    $media = function (): \App\Controllers\Dashboard\MediaController {
        $controller = App::container()->get(\App\Controllers\Dashboard\MediaController::class);
        setupController($controller, makeRequest('/dashboard/blog/'.$this->blogId.'/media'), $this->viewer);

        return $controller;
    };

    ($this->signInAs)('reviewer');
    expect(fn () => $media()->index((string) $this->blogId))->toThrow(UnauthorizedException::class);

    auth()->logout();
    ($this->signInAs)('author');
    expect($media()->index((string) $this->blogId)->getStatusCode())->toBe(200);
});

it('counts a post with two reviewers once on the Overview', function () {
    $this->db->query("UPDATE posts SET status = 'pending', workflow_state = 'in_review' WHERE id = ?", [$this->othersPostId]);
    for ($i = 0; $i < 2; $i++) {
        $reviewerId = UserFactory::new($this->users)->create();
        $this->blogs->addUserToBlog($this->blogId, $reviewerId, 'reviewer', $this->ownerId);
        $this->db->query('INSERT INTO post_reviewers (post_id, reviewer_id) VALUES (?, ?)', [$this->othersPostId, $reviewerId]);
    }
    ($this->signInAs)('editor');

    $overview = App::container()->get(OverviewService::class)->for($this->blogs->getBlog($this->blogId), auth()->user());

    expect($overview['review']['inReview'])->toBe(1)
        ->and($overview['review']['items'])->toHaveCount(1);
});

it('keeps blog deletion with the owner even when a custom role holds delete_own_blog', function () {
    $slug = 'partner_'.bin2hex(random_bytes(4));
    $this->db->query("INSERT INTO roles (role_name, role_slug, description, scope, is_system, level) VALUES ('Partner', ?, '', 'blog', 0, 60)", [$slug]);
    $this->db->query(
        "INSERT INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_slug IN ('view_all_posts', 'edit_blog_posts', 'delete_own_blog')
         WHERE r.role_slug = ?",
        [$slug]
    );
    ($this->signInAs)($slug);

    expect(\App\Gate::allows('delete', $this->blogs->getBlog($this->blogId), auth()->user()))->toBeFalse();

    auth()->logout();
    ($this->signInAs)('owner');
    expect(\App\Gate::allows('delete', $this->blogs->getBlog($this->blogId), auth()->user()))->toBeTrue();
});

it('finds reviewers by permission, custom roles and the owner included', function () {
    $slug = 'desk_'.bin2hex(random_bytes(4));
    $this->db->query("INSERT INTO roles (role_name, role_slug, description, scope, is_system, level) VALUES ('Desk', ?, '', 'blog', 0, 45)", [$slug]);
    $this->db->query(
        "INSERT INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_slug IN ('view_all_posts', 'review_posts')
         WHERE r.role_slug = ?",
        [$slug]
    );
    $deskId = UserFactory::new($this->users)->create();
    $this->blogs->addUserToBlog($this->blogId, $deskId, $slug, $this->ownerId);

    $ids = array_map('intval', array_column($this->blogs->getActiveUsersWithPermission($this->blogId, 'review_posts'), 'user_id'));

    expect($ids)->toContain($deskId)
        ->and($ids)->toContain($this->ownerId)
        ->and($ids)->not->toContain($this->otherWriterId);
});

it('lets a review-only role claim from the queue but not reassign others', function () {
    $slug = 'desk_'.bin2hex(random_bytes(4));
    $this->db->query("INSERT INTO roles (role_name, role_slug, description, scope, is_system, level) VALUES ('Desk', ?, '', 'blog', 0, 45)", [$slug]);
    $this->db->query(
        "INSERT INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_slug IN ('view_all_posts', 'review_posts', 'assign_reviewers')
         WHERE r.role_slug = ?",
        [$slug]
    );
    ($this->signInAs)($slug);

    $controller = App::container()->get(\App\Controllers\Dashboard\PostReviewController::class);
    setupController($controller, makeRequest('/dashboard/blog/'.$this->blogId.'/review-queue'), $this->viewer);
    $controller->reviewQueue((string) $this->blogId);

    expect($this->captured['canClaim'])->toBeTrue()
        ->and($this->captured['canManageReviewers'])->toBeFalse();
});

it('refuses to assign a reviewer who cannot review on the blog', function () {
    ($this->signInAs)('editor');

    $request = makeRequest('/dashboard/posts/'.$this->othersPostId.'/workflow/assign-reviewer', 'POST', [
        '_token' => App::container()->get(Csrf::class)->getToken(),
        'reviewer_id' => (string) $this->otherWriterId,
    ]);
    $controller = App::container()->get(\App\Controllers\Dashboard\PostReviewController::class);
    setupController($controller, $request, $this->viewer);
    $controller->assignReviewer((string) $this->othersPostId);

    $assigned = $this->db->query('SELECT COUNT(*) FROM post_reviewers WHERE post_id = ?', [$this->othersPostId])->fetchColumn();

    expect((int) $assigned)->toBe(0);
});

it('unpublishes a published post without granting it an approval', function () {
    ($this->signInAs)('editor');
    $this->db->query("UPDATE posts SET status = 'published', workflow_state = 'draft' WHERE id = ?", [$this->othersPostId]);

    ($this->statusController)('/dashboard/posts/'.$this->othersPostId.'/unpublish')->unpublish((string) $this->othersPostId);

    $post = $this->posts->find($this->othersPostId);

    expect($post['status'])->toBe('draft')
        ->and($post['workflow_state'])->toBe('draft');
});

it('refuses to unpublish a post that is not published', function () {
    ($this->signInAs)('editor');
    $this->db->query("UPDATE posts SET status = 'archived', workflow_state = 'needs_changes' WHERE id = ?", [$this->othersPostId]);

    ($this->statusController)('/dashboard/posts/'.$this->othersPostId.'/unpublish')->unpublish((string) $this->othersPostId);

    $post = $this->posts->find($this->othersPostId);

    expect($post['status'])->toBe('archived')
        ->and($post['workflow_state'])->toBe('needs_changes');
});
