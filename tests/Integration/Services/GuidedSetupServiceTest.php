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

test('new owner sees five steps with start done', function () {
    $setup = $this->setup->forUser($this->ownerId, $this->blogId);

    expect($setup['audience'])->toBe('owner')
        ->and($setup['state'])->toBe('open')
        ->and(($this->keysOf)($setup))->toBe(['start', 'look', 'profile', 'first_post', 'share'])
        ->and($setup['done'])->toBe(1)
        ->and($setup['total'])->toBe(5)
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

test('an offline blog shows the share step without a copy button', function () {
    $share = ($this->step)($this->setup->forUser($this->ownerId, $this->blogId), 'share');

    expect($share->done)->toBeFalse()
        ->and($share->actionLabelKey)->toBeNull()
        ->and($share->descriptionKey)->toBe('setup.steps.share.offline');

    $this->db->execute("UPDATE blogs SET status = 'published' WHERE id = ?", [$this->blogId]);
    $share = ($this->step)($this->setup->forUser($this->ownerId, $this->blogId), 'share');

    expect($share->actionLabelKey)->toBe('setup.steps.share.action')
        ->and($share->descriptionKey)->toBe('setup.steps.share.description');
});

test('a bio ticks the owner profile step', function () {
    $this->db->execute(
        'INSERT INTO user_profiles (user_id, bio) VALUES (?, ?) ON DUPLICATE KEY UPDATE bio = VALUES(bio)',
        [$this->ownerId, 'Writes about trains']
    );

    expect(($this->step)($this->setup->forUser($this->ownerId, $this->blogId), 'profile')->done)->toBeTrue();
});

test('readers outside the team tick the share step', function () {
    $this->db->execute("UPDATE blogs SET status = 'published' WHERE id = ?", [$this->blogId]);
    $postId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->ownerId, 'blog_id' => $this->blogId])
        ->published()
        ->create();
    $shared = fn () => ($this->step)($this->setup->forUser($this->ownerId, $this->blogId), 'share')->done;
    $subscribe = fn (?int $userId, string $email) => $this->db->execute(
        'INSERT INTO blog_subscribers (blog_id, user_id, email, token, confirmed_at) VALUES (?, ?, ?, ?, NOW())',
        [$this->blogId, $userId, $email, bin2hex(random_bytes(32))]
    );
    $comment = fn (int $userId) => $this->db->execute(
        "INSERT INTO comments (post_id, user_id, content, status) VALUES (?, ?, 'Nice', 'approved')",
        [$postId, $userId]
    );

    $subscribe($this->ownerId, 'owner@example.test');
    $editorId = UserFactory::new($this->users)->create();
    ($this->addMember)($this->blogId, $editorId, 'editor', $this->ownerId);
    $comment($editorId);
    $comment($this->ownerId);

    expect($shared())->toBeFalse();

    $comment(UserFactory::new($this->users)->create());

    expect($shared())->toBeTrue();
});

test('a confirmed subscriber ticks the share step', function () {
    $this->db->execute(
        'INSERT INTO blog_subscribers (blog_id, email, token, confirmed_at) VALUES (?, ?, ?, NOW())',
        [$this->blogId, 'reader@example.test', bin2hex(random_bytes(32))]
    );

    expect(($this->step)($this->setup->forUser($this->ownerId, $this->blogId), 'share')->done)->toBeTrue();
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
    PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->ownerId, 'blog_id' => $this->blogId])
        ->published()
        ->create();
    $this->marks->mark($this->ownerId, $this->blogId, 'look_chosen');
    $this->marks->mark($this->ownerId, $this->blogId, 'link_copied');
    $this->db->execute(
        'INSERT INTO user_profiles (user_id, bio) VALUES (?, ?) ON DUPLICATE KEY UPDATE bio = VALUES(bio)',
        [$this->ownerId, 'Writes about trains']
    );

    $setup = $this->setup->forUser($this->ownerId, $this->blogId);

    expect($setup['state'])->toBe('finished')
        ->and($setup['done'])->toBe(5);

    $this->marks->mark($this->ownerId, $this->blogId, 'hidden');
    $this->marks->mark($this->ownerId, $this->blogId, 'dismissed');

    expect($this->setup->forUser($this->ownerId, $this->blogId)['state'])->toBe('dismissed');
});

test('only the owner\'s first blog gets the card', function () {
    $secondBlogId = BlogFactory::new($this->blogs)->draft()->create($this->ownerId);
    $hostId = UserFactory::new($this->users)->create();
    $joinedBlogId = BlogFactory::new($this->blogs)->create($hostId);
    ($this->addMember)($joinedBlogId, $this->ownerId, 'author', $hostId);

    $second = $this->setup->forUser($this->ownerId, $secondBlogId);

    expect($second['state'])->toBe('dismissed')
        ->and($second['steps'])->toBe([])
        ->and($this->setup->forUser($this->ownerId, $this->blogId)['state'])->toBe('open')
        ->and($this->setup->forUser($this->ownerId, $joinedBlogId)['state'])->toBe('open');
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
    // The roles table survives between runs, so the name has to be as unique as the slug
    $this->db->query("INSERT INTO roles (role_name, role_slug, description, scope, is_system, level) VALUES (?, ?, '', 'blog', 0, 60)", [$slug, $slug]);
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
        ->and(($this->keysOf)($setup))->toBe(['start', 'look', 'profile', 'first_post', 'share'])
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
