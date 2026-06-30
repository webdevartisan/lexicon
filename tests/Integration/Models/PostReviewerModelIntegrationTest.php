<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\PostReviewerModel;
use App\Models\ReviewModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for PostReviewerModel and ReviewModel.
 *
 * Exercises reviewer assignment and review-feedback persistence against a real DB.
 */
beforeEach(function () {
    $this->blogModel = new BlogModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->postModel = new PostModel($this->db);
    $this->reviewerModel = new PostReviewerModel($this->db);
    $this->reviewModel = new ReviewModel($this->db);

    $this->ownerId = UserFactory::new($this->userModel)->create();
    $this->reviewerId = UserFactory::new($this->userModel)->create();
    $this->blogId = BlogFactory::new($this->blogModel)->create($this->ownerId);
    $this->postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $this->ownerId, 'blog_id' => $this->blogId])
        ->create();
});

test('assign creates a reviewer assignment', function () {
    expect($this->reviewerModel->assign($this->postId, $this->reviewerId, $this->ownerId))->toBeTrue();

    $assignments = $this->reviewerModel->findByPost($this->postId);
    expect($assignments)->toHaveCount(1)
        ->and((int) $assignments[0]['reviewer_id'])->toBe($this->reviewerId);
});

test('assign is idempotent for the same reviewer', function () {
    $this->reviewerModel->assign($this->postId, $this->reviewerId, $this->ownerId);
    $this->reviewerModel->assign($this->postId, $this->reviewerId, $this->ownerId);

    expect($this->reviewerModel->findByPost($this->postId))->toHaveCount(1);
});

test('clearByPost removes all reviewer assignments', function () {
    $this->reviewerModel->assign($this->postId, $this->reviewerId, $this->ownerId);
    expect($this->reviewerModel->clearByPost($this->postId))->toBeTrue();

    expect($this->reviewerModel->findByPost($this->postId))->toHaveCount(0);
});

test('ReviewModel stores a decision and feedback', function () {
    expect($this->reviewModel->create($this->postId, $this->reviewerId, 'needs_revision', 'Tighten the intro.'))->toBeTrue();

    $reviews = $this->reviewModel->findByPost($this->postId);
    expect($reviews)->toHaveCount(1)
        ->and($reviews[0]['decision'])->toBe('needs_revision')
        ->and($reviews[0]['feedback'])->toBe('Tighten the intro.');
});

test('ReviewModel rejects an invalid decision', function () {
    expect(fn () => $this->reviewModel->create($this->postId, $this->reviewerId, 'bogus', ''))
        ->toThrow(\InvalidArgumentException::class);
});
