<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use App\Models\TrafficEventModel;
use App\Models\UserModel;
use App\Services\Traffic\GoalRecorder;
use App\Services\Traffic\LexiconSource;
use App\Services\Traffic\TrafficSettings;
use App\Services\Traffic\UserAgentClassifier;
use App\ValueObjects\TrafficScope;
use Framework\Core\Request;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $users = new UserModel($this->db);
    $blogs = new BlogModel($this->db);
    $posts = new PostModel($this->db);
    $config = require ROOT_PATH.'/config/traffic.php';

    $this->ownerId = UserFactory::new($users)->create();
    $this->readerId = UserFactory::new($users)->create();
    $this->blogId = BlogFactory::new($blogs)->published()->create($this->ownerId);
    $this->postId = PostFactory::new($posts)->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->ownerId])->published()->create();

    $this->events = new TrafficEventModel($this->db);
    $this->goals = new GoalRecorder(
        new TrafficSettings(new SettingModel($this->db)),
        new UserAgentClassifier($config['bot_patterns']),
        $this->events,
        $blogs,
        $posts,
    );

    $this->request = static fn (array $headers = []): Request => new Request(
        '/posts/1/vote', 'POST', [], [], [], [], ['REMOTE_ADDR' => '203.0.113.5'],
        array_change_key_case($headers + ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/129.0 Safari/537.36'])
    );
    $this->today = (new DateTimeImmutable('now', new DateTimeZone(blog_timezone($this->blogId))))->format('Y-m-d');
});

test('a reader reaching a goal is counted on the blog and the post, without who', function () {
    $reader = ['id' => $this->readerId, 'roles' => ['reader']];

    $this->goals->onPost(($this->request)(), 'like', $this->postId, $reader);
    $this->goals->onPost(($this->request)(), 'comment', $this->postId, null);
    $this->goals->onBlog(($this->request)(), 'subscribe', $this->blogId, null);
    $this->events->recordSignup(['channel' => 'direct', 'came_from' => LexiconSource::blog($this->blogId)]);

    $blog = $this->events->goalCounts(TrafficScope::blog($this->blogId), $this->today, $this->today);
    $post = $this->events->goalCounts(TrafficScope::post($this->blogId, $this->postId), $this->today, $this->today);

    expect($blog)->toBe(['subscribe' => 1, 'comment' => 1, 'like' => 1, 'save' => 0, 'signup' => 1])
        ->and($post)->toBe(['subscribe' => 0, 'comment' => 1, 'like' => 1, 'save' => 0, 'signup' => 0])
        ->and($this->db->query('SELECT COUNT(*) FROM traffic_events WHERE event <> \'signup\' AND (came_from IS NOT NULL OR channel IS NOT NULL)')->fetchColumn())->toBe(0);
});

test('the blog team, administrators, crawlers and readers who opted out are not counted', function () {
    $this->goals->onPost(($this->request)(), 'like', $this->postId, ['id' => $this->ownerId, 'roles' => ['reader']]);
    $this->goals->onPost(($this->request)(), 'like', $this->postId, ['id' => $this->readerId, 'roles' => ['administrator']]);
    $this->goals->onPost(($this->request)(['User-Agent' => 'Googlebot/2.1']), 'like', $this->postId, null);
    $this->goals->onPost(($this->request)(['Sec-GPC' => '1']), 'like', $this->postId, null);

    expect((int) $this->db->query('SELECT COUNT(*) FROM traffic_events')->fetchColumn())->toBe(0);
});

test('clicks are listed by host or file, most clicked first', function () {
    foreach (['wikipedia.org', 'github.com', 'wikipedia.org'] as $host) {
        $this->events->recordClick('outbound', $this->blogId, $this->postId, $host, $this->today);
    }

    expect($this->events->clickBreakdown('outbound', TrafficScope::blog($this->blogId), $this->today, $this->today, 10))->toBe([
        ['value' => 'wikipedia.org', 'clicks' => 2],
        ['value' => 'github.com', 'clicks' => 1],
    ]);
});
