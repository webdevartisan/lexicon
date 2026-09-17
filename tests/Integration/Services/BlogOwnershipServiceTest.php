<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\UserModel;
use App\Services\BlogOwnershipService;
use App\Services\PublicCacheInvalidator;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->service = new BlogOwnershipService($this->blogs, Mockery::mock(PublicCacheInvalidator::class)->shouldIgnoreMissing());

    $this->ownerId = UserFactory::new($this->users)->create();
    $this->memberId = UserFactory::new($this->users)->create();
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($this->ownerId);
});

afterEach(function () {
    Mockery::close();
});

test('a collaborator becomes the owner and the previous owner stays on as an editor', function () {
    $this->blogs->addUserToBlog($this->blogId, $this->memberId, 'author', $this->ownerId);

    $this->service->transfer($this->blogId, $this->ownerId, $this->memberId, $this->ownerId, '127.0.0.1');

    expect((int) $this->blogs->find($this->blogId)['owner_id'])->toBe($this->memberId)
        ->and($this->blogs->storedRoleFor($this->blogId, $this->memberId))->toBeNull()
        ->and($this->blogs->storedRoleFor($this->blogId, $this->ownerId))->toBe('editor');
});

test('ownership can only go to an active collaborator', function () {
    expect(fn () => $this->service->transfer($this->blogId, $this->ownerId, $this->memberId, $this->ownerId, null))
        ->toThrow(InvalidArgumentException::class);

    expect((int) $this->blogs->find($this->blogId)['owner_id'])->toBe($this->ownerId);
});
