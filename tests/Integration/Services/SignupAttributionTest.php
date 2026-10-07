<?php

declare(strict_types=1);

use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsSaltModel;
use App\Models\AnalyticsVisitModel;
use App\Models\SettingModel;
use App\Services\Analytics\AnalyticsSettings;
use App\Services\Analytics\SignupAttribution;
use App\Services\Analytics\UserAgentClassifier;
use App\Services\Analytics\VisitFinder;
use App\Services\Analytics\VisitorCookie;
use App\Services\Analytics\VisitorIdentity;
use Framework\Core\Request;
use Tests\Helpers\AnalyticsFixture;

const SIGNUP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

beforeEach(function () {
    $config = require ROOT_PATH.'/config/analytics.php';

    $this->identity = new VisitorIdentity(new AnalyticsSaltModel($this->db), 'test-key');
    $this->attribution = new SignupAttribution(
        new AnalyticsSettings(new SettingModel($this->db)),
        new UserAgentClassifier($config['bot_patterns']),
        new VisitFinder($this->identity, new VisitorCookie('lx_vid', 395), new AnalyticsVisitModel($this->db), 30),
        new AnalyticsEventModel($this->db),
    );

    $this->request = static fn (array $headers = [], array $cookies = []): Request => new Request(
        '/register/submit', 'POST', [], [], [], $cookies, ['REMOTE_ADDR' => '203.0.113.50'],
        array_change_key_case($headers + ['User-Agent' => SIGNUP_UA])
    );

    // A view by this browser, $minutesAgo minutes back, in the visit named $visit.
    $this->view = function (string $hash, int $minutesAgo, string $pageType, ?int $blogId, string $channel, ?string $source = null, ?string $campaign = null, string $visit = 'current') {
        AnalyticsFixture::view($this->db, [
            'blog_id' => $blogId,
            'page_type' => $pageType,
            'path' => '/x',
            'visitor_hash' => $hash,
            'visit' => bin2hex($hash).$visit,
            'channel' => $channel,
            'referrer_source' => $source,
            'utm_campaign' => $campaign,
            'browser' => 'Chrome',
            'os' => 'Windows',
            'locale' => 'en',
            'created_at' => gmdate('Y-m-d H:i:s', time() - $minutesAgo * 60),
        ]);
    };

    $this->daily = $this->identity->dailyFor(($this->request)(), new DateTimeImmutable('now', new DateTimeZone('UTC')));
});

function signupEvents(Framework\Database $db): array
{
    return $db->query(
        "SELECT channel, referrer_source, utm_campaign, JSON_UNQUOTE(JSON_EXTRACT(props, '$.came_from')) AS came_from FROM analytics_events WHERE name = 'signup'"
    )->fetchAll(PDO::FETCH_ASSOC);
}

test('a sign-up keeps how its visit began and the last page read before signing up', function () {
    ($this->view)($this->daily, 20, 'post', 7, 'search', 'Google', 'october');
    ($this->view)($this->daily, 12, 'landing', 7, 'internal');
    ($this->view)($this->daily, 2, 'auth', null, 'lexicon', 'blog:7');

    expect($this->attribution->record(($this->request)()))->toBeTrue()
        ->and(signupEvents($this->db))->toBe([
            ['channel' => 'search', 'referrer_source' => 'Google', 'utm_campaign' => 'october', 'came_from' => 'blog:7'],
        ]);
});

test('an earlier visit that day is not where this one began', function () {
    ($this->view)($this->daily, 300, 'post', 7, 'social', 'Reddit', null, 'morning');
    ($this->view)($this->daily, 10, 'discover', null, 'direct');

    $this->attribution->record(($this->request)());

    expect(signupEvents($this->db))->toBe([
        ['channel' => 'direct', 'referrer_source' => null, 'utm_campaign' => null, 'came_from' => 'discover'],
    ]);
});

test('a visit that began elsewhere on Lexicon counts as moving around the site', function () {
    ($this->view)($this->daily, 5, 'landing', 7, 'lexicon', 'other');

    $this->attribution->record(($this->request)());

    expect(signupEvents($this->db)[0]['channel'])->toBe('internal')
        ->and(signupEvents($this->db)[0]['referrer_source'])->toBeNull();
});

test('a reader with the analytics cookie is found by it', function () {
    $cookieId = str_repeat('ab', 16);
    ($this->view)($this->identity->forCookie($cookieId), 3, 'home', null, 'direct');

    expect($this->attribution->record(($this->request)([], ['lx_vid' => $cookieId])))->toBeTrue()
        ->and(signupEvents($this->db)[0]['came_from'])->toBe('home');
});

test('going straight to sign-up is noted as the sign-up page', function () {
    ($this->view)($this->daily, 1, 'auth', null, 'email', 'Gmail');

    $this->attribution->record(($this->request)());

    expect(signupEvents($this->db)[0]['came_from'])->toBe('auth');
});

test('nothing is noted without a counted visit going on', function (array $headers, int $minutesAgo) {
    ($this->view)($this->daily, $minutesAgo, 'home', null, 'direct');

    expect($this->attribution->record(($this->request)($headers)))->toBeFalse()
        ->and(signupEvents($this->db))->toBe([]);
})->with([
    'the last view was over half an hour ago' => [[], 45],
    'global privacy control' => [['Sec-GPC' => '1'], 1],
    'do not track' => [['DNT' => '1'], 1],
    'a crawler' => [['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'], 1],
]);

test('nothing is noted while counting is switched off', function () {
    ($this->view)($this->daily, 1, 'home', null, 'direct');
    (new SettingModel($this->db))->set('analytics.enabled', '0');

    expect($this->attribution->record(($this->request)()))->toBeFalse();
});
