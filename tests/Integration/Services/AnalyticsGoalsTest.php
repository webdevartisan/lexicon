<?php

declare(strict_types=1);

use App\Models\AnalyticsDailyEventModel;
use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsSaltModel;
use App\Models\AnalyticsVisitModel;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\Analytics\AnalyticsSettings;
use App\Services\Analytics\GoalRecorder;
use App\Services\Analytics\LexiconSource;
use App\Services\Analytics\UserAgentClassifier;
use App\Services\Analytics\VisitFinder;
use App\Services\Analytics\VisitorCookie;
use App\Services\Analytics\VisitorIdentity;
use App\ValueObjects\AnalyticsScope;
use Framework\Core\Request;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\AnalyticsFixture;

beforeEach(function () {
    $users = new UserModel($this->db);
    $blogs = new BlogModel($this->db);
    $posts = new PostModel($this->db);
    $config = require ROOT_PATH.'/config/analytics.php';

    $this->ownerId = UserFactory::new($users)->create();
    $this->readerId = UserFactory::new($users)->create();
    $this->blogId = BlogFactory::new($blogs)->published()->create($this->ownerId);
    $this->postId = PostFactory::new($posts)->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->ownerId])->published()->create();

    $this->identity = new VisitorIdentity(new AnalyticsSaltModel($this->db), 'test-key');
    $this->events = new AnalyticsDailyEventModel($this->db);
    $this->goals = new GoalRecorder(
        new AnalyticsSettings(new SettingModel($this->db)),
        new UserAgentClassifier($config['bot_patterns']),
        new VisitFinder($this->identity, new VisitorCookie('lx_vid', 395), new AnalyticsVisitModel($this->db), 30),
        new AnalyticsEventModel($this->db),
        AnalyticsFixture::registry(),
        $blogs,
        $posts,
    );

    $this->request = static fn (array $headers = []): Request => new Request(
        '/posts/1/vote', 'POST', [], [], [], [], ['REMOTE_ADDR' => '203.0.113.5'],
        array_change_key_case($headers + ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/129.0 Safari/537.36'])
    );
    $this->today = (new DateTimeImmutable('now', new DateTimeZone(blog_timezone($this->blogId))))->format('Y-m-d');
    $this->rollUp = fn () => AnalyticsFixture::rollups($this->db)->rebuildFrom(gmdate('Y-m-d', strtotime('-2 days')), 30, 10);
});

test('a reader reaching a goal is counted on the blog and the post', function () {
    $reader = ['id' => $this->readerId, 'roles' => ['reader']];

    $this->goals->onPost(($this->request)(), 'like', $this->postId, $reader);
    $this->goals->onPost(($this->request)(), 'comment', $this->postId, null);
    $this->goals->onBlog(($this->request)(), 'subscribe', $this->blogId, null);
    AnalyticsFixture::event($this->db, ['name' => 'signup', 'channel' => 'direct', 'props' => ['came_from' => LexiconSource::blog($this->blogId)]]);
    ($this->rollUp)();

    $blog = $this->events->goalCounts(AnalyticsScope::blog($this->blogId), $this->today, $this->today);
    $post = $this->events->goalCounts(AnalyticsScope::post($this->blogId, $this->postId), $this->today, $this->today);

    expect($blog)->toBe(['subscribe' => 1, 'comment' => 1, 'like' => 1, 'save' => 0, 'share' => 0, 'signup' => 1])
        ->and($post)->toBe(['subscribe' => 0, 'comment' => 1, 'like' => 1, 'save' => 0, 'share' => 0, 'signup' => 0]);
});

test('a goal outside a counted visit says nothing about who reached it', function () {
    $this->goals->onPost(($this->request)(), 'like', $this->postId, ['id' => $this->readerId, 'roles' => ['reader']]);

    expect($this->db->query("SELECT visit_id, visitor_hash, channel FROM analytics_events WHERE name = 'like'")->fetch(PDO::FETCH_ASSOC))
        ->toBe(['visit_id' => null, 'visitor_hash' => null, 'channel' => null]);
});

test('a goal joins the visit the reader is on, and a like counts once in it', function () {
    $daily = $this->identity->dailyFor(($this->request)(), new DateTimeImmutable('now', new DateTimeZone('UTC')));
    AnalyticsFixture::view($this->db, [
        'blog_id' => $this->blogId,
        'post_id' => $this->postId,
        'path' => '/blog/b/p',
        'visitor_hash' => $daily,
        'visit' => 'reading',
        'channel' => 'search',
        'referrer_source' => 'Google',
    ]);

    $this->goals->onPost(($this->request)(), 'like', $this->postId, null);
    $this->goals->onPost(($this->request)(), 'like', $this->postId, null);

    expect($this->db->query("SELECT visit_id, channel, referrer_source FROM analytics_events WHERE name = 'like'")->fetchAll(PDO::FETCH_ASSOC))
        ->toBe([['visit_id' => md5('visit:reading', true), 'channel' => 'search', 'referrer_source' => 'Google']]);
});

test('the blog team, administrators, crawlers and readers who opted out are not counted', function () {
    $this->goals->onPost(($this->request)(), 'like', $this->postId, ['id' => $this->ownerId, 'roles' => ['reader']]);
    $this->goals->onPost(($this->request)(), 'like', $this->postId, ['id' => $this->readerId, 'roles' => ['administrator']]);
    $this->goals->onPost(($this->request)(['User-Agent' => 'Googlebot/2.1']), 'like', $this->postId, null);
    $this->goals->onPost(($this->request)(['Sec-GPC' => '1']), 'like', $this->postId, null);

    expect((int) $this->db->query('SELECT COUNT(*) FROM analytics_events')->fetchColumn())->toBe(0);
});

test('an event the registry does not know is refused', function () {
    $this->goals->onPost(($this->request)(), 'clap', $this->postId, null);
})->throws(InvalidArgumentException::class);

test('clicks and shares are listed by host, file or network, most clicked first', function () {
    foreach ([['outbound', 'host', 'wikipedia.org'], ['outbound', 'host', 'github.com'], ['outbound', 'host', 'wikipedia.org'], ['share', 'network', 'x']] as [$name, $prop, $value]) {
        AnalyticsFixture::event($this->db, ['name' => $name, 'blog_id' => $this->blogId, 'post_id' => $this->postId, 'props' => [$prop => $value]]);
    }
    ($this->rollUp)();

    expect($this->events->clickBreakdown('outbound', AnalyticsScope::blog($this->blogId), $this->today, $this->today, 10))->toBe([
        ['value' => 'wikipedia.org', 'clicks' => 2],
        ['value' => 'github.com', 'clicks' => 1],
    ])
        ->and($this->events->clickBreakdown('share', AnalyticsScope::post($this->blogId, $this->postId), $this->today, $this->today, 10))->toBe([
            ['value' => 'x', 'clicks' => 1],
        ]);
});
