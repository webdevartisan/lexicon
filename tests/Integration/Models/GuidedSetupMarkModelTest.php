<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\GuidedSetupMarkModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $this->marks = new GuidedSetupMarkModel($this->db);
    $this->userId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->create($this->userId);
});

test('marking twice keeps one row', function () {
    $this->marks->mark($this->userId, $this->blogId, 'link_copied');
    $this->marks->mark($this->userId, $this->blogId, 'link_copied');

    $rows = (int) $this->db->query(
        'SELECT COUNT(*) FROM guided_setup_marks WHERE user_id = ? AND blog_id = ?',
        [$this->userId, $this->blogId]
    )->fetchColumn();

    expect($rows)->toBe(1)
        ->and($this->marks->marksFor($this->userId, $this->blogId))->toBe(['link_copied' => true]);
});

test('marksFor returns only that user and blog', function () {
    $otherUserId = UserFactory::new(new UserModel($this->db))->create();
    $otherBlogId = BlogFactory::new(new BlogModel($this->db))->create($this->userId);

    $this->marks->mark($this->userId, $this->blogId, 'hidden');
    $this->marks->mark($this->userId, $otherBlogId, 'link_copied');
    $this->marks->mark($otherUserId, $this->blogId, 'dismissed');

    expect($this->marks->marksFor($this->userId, $this->blogId))->toBe(['hidden' => true]);

    $this->marks->unmark($this->userId, $this->blogId, 'hidden');

    expect($this->marks->marksFor($this->userId, $this->blogId))->toBe([]);
});

test('unknown mark throws', function () {
    $this->marks->mark($this->userId, $this->blogId, 'finished_everything');
})->throws(InvalidArgumentException::class);

test('deleting the blog removes its marks', function () {
    $this->marks->mark($this->userId, $this->blogId, 'look_chosen');

    $this->db->execute('DELETE FROM blogs WHERE id = ?', [$this->blogId]);

    $rows = (int) $this->db->query(
        'SELECT COUNT(*) FROM guided_setup_marks WHERE blog_id = ?',
        [$this->blogId]
    )->fetchColumn();

    expect($rows)->toBe(0);
});
