<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogSubscriberModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $this->subscribers = new BlogSubscriberModel($this->db);
    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($ownerId);
});

test('an unconfirmed address gets no post notifications until its link is followed', function () {
    $subscription = $this->subscribers->subscribe($this->blogId, 'someone@example.test');

    expect($subscription['confirmed_at'])->toBeNull()
        ->and($this->subscribers->forBlog($this->blogId))->toBe([])
        ->and($this->subscribers->countForBlog($this->blogId))->toBe(0)
        ->and($this->subscribers->pageForBlog($this->blogId)['data'])->toBe([]);

    $this->subscribers->confirmByToken($subscription['token']);

    expect($this->subscribers->forBlog($this->blogId))->toHaveCount(1);
});

test('subscribing again keeps the token and never undoes a confirmation', function () {
    $first = $this->subscribers->subscribe($this->blogId, 'someone@example.test', null, true);
    $again = $this->subscribers->subscribe($this->blogId, 'someone@example.test');

    expect($again['token'])->toBe($first['token'])
        ->and($again['confirmed_at'])->not->toBeNull();
});

test('an unknown confirmation token confirms nothing', function () {
    expect($this->subscribers->confirmByToken(str_repeat('a', 64)))->toBeNull();
});

test('only unconfirmed subscriptions past the retention period are removed', function () {
    $this->subscribers->subscribe($this->blogId, 'old@example.test');
    $this->subscribers->subscribe($this->blogId, 'new@example.test');
    $this->subscribers->subscribe($this->blogId, 'kept@example.test', null, true);
    $this->db->execute("UPDATE blog_subscribers SET created_at = NOW() - INTERVAL 8 DAY WHERE email IN ('old@example.test', 'kept@example.test')");

    expect($this->subscribers->deleteUnconfirmedOlderThan(7))->toBe(1);

    $left = $this->db->query('SELECT email FROM blog_subscribers ORDER BY email')->fetchAll(\PDO::FETCH_COLUMN);

    expect($left)->toBe(['kept@example.test', 'new@example.test']);
});
