<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * Blog roles live in blog memberships and Blog Owner on the blogs themselves,
 * not in user_roles. The count also guards deletion, so a wrong 0 would let an
 * admin delete a role people still hold.
 */
beforeEach(function () {
    $this->roles = new RoleModel($this->db);
    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);

    $slug = 'count_check_'.bin2hex(random_bytes(3));
    $this->db->execute(
        "INSERT INTO roles (role_name, role_slug, description, scope, is_system, level) VALUES (?, ?, 'Counted in tests', 'blog', 0, 25)",
        ['Count check '.$slug, $slug]
    );
    $this->customRoleId = (int) $this->db->getConnection()->lastInsertId();
    $this->customSlug = $slug;

    $this->joinBlog = function (int $blogId, int $userId, bool $active = true): void {
        $this->db->execute(
            'INSERT INTO blog_users (blog_id, user_id, role, is_active, revoked_at) VALUES (?, ?, ?, ?, ?)',
            [$blogId, $userId, $this->customSlug, $active ? 1 : 0, $active ? null : gmdate('Y-m-d H:i:s')]
        );
    };
});

it('counts each blog owner once, however many blogs they own', function () {
    $ownerRoleId = (int) $this->roles->findBySlug('blog_owner')['id'];
    $before = $this->roles->userCount($ownerRoleId);

    $ownerId = UserFactory::new($this->users)->create();
    BlogFactory::new($this->blogs)->create($ownerId);
    BlogFactory::new($this->blogs)->create($ownerId);

    expect($this->roles->userCount($ownerRoleId))->toBe($before + 1);
});

it('counts active members of a blog role and leaves revoked ones out', function () {
    $ownerId = UserFactory::new($this->users)->create();
    $blogId = BlogFactory::new($this->blogs)->create($ownerId);
    ($this->joinBlog)($blogId, UserFactory::new($this->users)->create());
    ($this->joinBlog)($blogId, UserFactory::new($this->users)->create(), false);

    $listed = array_values(array_filter(
        $this->roles->findAllWithUserCounts(),
        fn (array $role): bool => (int) $role['id'] === $this->customRoleId
    ));

    expect($this->roles->userCount($this->customRoleId))->toBe(1)
        ->and((int) $listed[0]['users_count'])->toBe(1);
});

it('refuses to delete a custom blog role someone still holds', function () {
    $ownerId = UserFactory::new($this->users)->create();
    $blogId = BlogFactory::new($this->blogs)->create($ownerId);
    ($this->joinBlog)($blogId, UserFactory::new($this->users)->create());

    expect($this->roles->deleteRole($this->customRoleId))->toBeFalse()
        ->and($this->roles->find($this->customRoleId))->not->toBeNull();
});
