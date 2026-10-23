<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\GuidedSetupFactsModel;
use App\Models\GuidedSetupMarkModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\GuidedSetupService;
use App\ValueObjects\SetupStep;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->posts = new PostModel($this->db);
    $this->marks = new GuidedSetupMarkModel($this->db);
    $this->setup = new GuidedSetupService($this->blogs, $this->marks, new GuidedSetupFactsModel($this->db));

    $this->ownerId = UserFactory::new($this->users)->create();
    // The factory fills in a description, which would tick the look step on a blog that's brand new
    $this->blogId = BlogFactory::new($this->blogs)->draft()->withAttributes(['description' => ''])->create($this->ownerId);

    $this->addMember = function (int $blogId, int $userId, string $role, int $hostId) {
        $this->db->query(
            'INSERT INTO blog_users (blog_id, user_id, role, assigned_by, assigned_at, is_active)
             VALUES (?, ?, ?, ?, NOW(), 1)',
            [$blogId, $userId, $role, $hostId]
        );
    };

    $this->keysOf = fn (array $setup) => array_map(fn (SetupStep $step) => $step->key, $setup['steps']);

    $this->step = function (array $setup, string $key): SetupStep {
        foreach ($setup['steps'] as $step) {
            if ($step->key === $key) {
                return $step;
            }
        }

        throw new RuntimeException("No step {$key}");
    };
});

test('new owner sees six steps with start done', function () {
    $setup = $this->setup->forUser($this->ownerId, $this->blogId);

    expect($setup['audience'])->toBe('owner')
        ->and($setup['state'])->toBe('open')
        ->and(($this->keysOf)($setup))->toBe(['start', 'first_post', 'look', 'share', 'followers', 'team'])
        ->and($setup['done'])->toBe(1)
        ->and($setup['total'])->toBe(6)
        ->and(($this->step)($setup, 'start')->done)->toBeTrue()
        ->and(($this->step)($setup, 'first_post')->done)->toBeFalse();
});

test('publishing a post ticks first_post', function () {
    PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->ownerId, 'blog_id' => $this->blogId])
        ->published()
        ->create();

    $setup = $this->setup->forUser($this->ownerId, $this->blogId);

    expect(($this->step)($setup, 'first_post')->done)->toBeTrue()
        ->and(($this->step)($setup, 'first_post')->doneNoteKey)->toBe('setup.steps.first_post.done')
        ->and($setup['done'])->toBe(2);
});

test('description alone ticks look', function () {
    $this->db->execute('UPDATE blogs SET description = ? WHERE id = ?', ['Notes on slow travel', $this->blogId]);

    $setup = $this->setup->forUser($this->ownerId, $this->blogId);

    expect(($this->step)($setup, 'look')->done)->toBeTrue();
});

test('owner and collaborator cards are separate per blog', function () {
    $hostId = UserFactory::new($this->users)->create();
    $otherBlogId = BlogFactory::new($this->blogs)->create($hostId);
    ($this->addMember)($otherBlogId, $this->ownerId, 'author', $hostId);

    $this->marks->mark($this->ownerId, $this->blogId, 'link_copied');
    $this->marks->mark($this->ownerId, $otherBlogId, 'hidden');

    $own = $this->setup->forUser($this->ownerId, $this->blogId);
    $joined = $this->setup->forUser($this->ownerId, $otherBlogId);

    expect($own['audience'])->toBe('owner')
        ->and($own['state'])->toBe('open')
        ->and(($this->step)($own, 'share')->done)->toBeTrue()
        ->and($joined['audience'])->toBe('member')
        ->and($joined['state'])->toBe('hidden')
        ->and(($this->keysOf)($joined))->not->toContain('share');
});

test('author on a review blog gets member_review and no publish step', function () {
    $authorId = UserFactory::new($this->users)->create();
    ($this->addMember)($this->blogId, $authorId, 'author', $this->ownerId);
    $this->db->execute(
        'INSERT INTO blog_settings (blog_id, workflow_enabled) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE workflow_enabled = 1',
        [$this->blogId]
    );

    $setup = $this->setup->forUser($authorId, $this->blogId);

    expect($setup['audience'])->toBe('member')
        ->and(($this->keysOf)($setup))->toBe(['member_first_post', 'member_review', 'member_profile'])
        ->and(($this->step)($setup, 'member_review')->actionUrl)->toBeNull();

    PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $authorId, 'blog_id' => $this->blogId, 'status' => 'pending'])
        ->create();

    $setup = $this->setup->forUser($authorId, $this->blogId);

    expect(($this->step)($setup, 'member_first_post')->done)->toBeTrue()
        ->and(($this->step)($setup, 'member_review')->done)->toBeTrue()
        ->and(($this->step)($setup, 'member_profile')->done)->toBeFalse();
});

test('all done gives finished, and dismissed wins over finished', function () {
    $friendId = UserFactory::new($this->users)->create();
    PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->ownerId, 'blog_id' => $this->blogId])
        ->published()
        ->create();
    $this->marks->mark($this->ownerId, $this->blogId, 'look_chosen');
    $this->marks->mark($this->ownerId, $this->blogId, 'link_copied');
    $this->marks->mark($this->ownerId, $this->blogId, 'subscribers_seen');
    ($this->addMember)($this->blogId, $friendId, 'editor', $this->ownerId);

    $setup = $this->setup->forUser($this->ownerId, $this->blogId);

    expect($setup['state'])->toBe('finished')
        ->and($setup['done'])->toBe(6);

    $this->marks->mark($this->ownerId, $this->blogId, 'hidden');
    $this->marks->mark($this->ownerId, $this->blogId, 'dismissed');

    expect($this->setup->forUser($this->ownerId, $this->blogId)['state'])->toBe('dismissed');
});

test('stranger gets null', function () {
    $strangerId = UserFactory::new($this->users)->create();

    expect($this->setup->forUser($strangerId, $this->blogId))->toBeNull()
        ->and($this->setup->forUser($this->ownerId, 999999))->toBeNull();
});

test('a reviewer who cannot write gets no review explainer', function () {
    $reviewerId = UserFactory::new($this->users)->create();
    ($this->addMember)($this->blogId, $reviewerId, 'reviewer', $this->ownerId);
    $this->db->execute(
        'INSERT INTO blog_settings (blog_id, workflow_enabled) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE workflow_enabled = 1',
        [$this->blogId]
    );

    expect(($this->keysOf)($this->setup->forUser($reviewerId, $this->blogId)))->toBe(['reviewer_profile']);
});

test('a co-owner shares the blog steps their role can act on', function () {
    $slug = 'partner_'.bin2hex(random_bytes(4));
    $this->db->query("INSERT INTO roles (role_name, role_slug, description, scope, is_system, level) VALUES ('Partner', ?, '', 'blog', 0, 60)", [$slug]);
    $this->db->query(
        "INSERT INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_slug IN ('create_posts', 'edit_own_blog', 'delete_own_blog')
         WHERE r.role_slug = ?",
        [$slug]
    );
    $partnerId = UserFactory::new($this->users)->create();
    ($this->addMember)($this->blogId, $partnerId, $slug, $this->ownerId);
    $this->db->execute('UPDATE blogs SET description = ? WHERE id = ?', ['Notes on slow travel', $this->blogId]);

    $setup = $this->setup->forUser($partnerId, $this->blogId);

    expect($setup['audience'])->toBe('co_owner')
        ->and(($this->keysOf)($setup))->toBe(['start', 'first_post', 'look', 'share'])
        ->and(($this->step)($setup, 'look')->done)->toBeTrue();
});

test('a profile filled in before joining ticks member_profile', function () {
    $authorId = UserFactory::new($this->users)->create();
    ($this->addMember)($this->blogId, $authorId, 'author', $this->ownerId);
    $this->db->execute(
        'INSERT INTO user_profiles (user_id, bio) VALUES (?, ?) ON DUPLICATE KEY UPDATE bio = VALUES(bio)',
        [$authorId, 'Writes about trains']
    );

    expect(($this->step)($this->setup->forUser($authorId, $this->blogId), 'member_profile')->done)->toBeTrue();
});

test('a contributor on a blog without review finishes the post step with a draft', function () {
    $contributorId = UserFactory::new($this->users)->create();
    ($this->addMember)($this->blogId, $contributorId, 'contributor', $this->ownerId);
    PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $contributorId, 'blog_id' => $this->blogId, 'status' => 'draft'])
        ->create();

    expect(($this->step)($this->setup->forUser($contributorId, $this->blogId), 'member_first_post')->done)->toBeTrue();

    $this->db->execute(
        'INSERT INTO blog_settings (blog_id, workflow_enabled) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE workflow_enabled = 1',
        [$this->blogId]
    );

    expect(($this->step)($this->setup->forUser($contributorId, $this->blogId), 'member_first_post')->done)->toBeFalse();
});
