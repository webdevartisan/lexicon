<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use App\Models\TagModel;
use App\Models\TrafficHitModel;
use App\Models\TrafficSaltModel;
use App\Models\UserModel;
use App\Privacy\Consent;
use App\Privacy\ConsentCookieStore;
use App\Services\ConsentService;
use App\Services\LocaleRegistry;
use App\Services\Traffic\BeaconPayload;
use App\Services\Traffic\CountryLookup;
use App\Services\Traffic\PagePathResolver;
use App\Services\Traffic\ReferrerClassifier;
use App\Services\Traffic\TrafficRecorder;
use App\Services\Traffic\TrafficSettings;
use App\Services\Traffic\UserAgentClassifier;
use App\Services\Traffic\VisitorCookie;
use App\Services\Traffic\VisitorIdentity;
use App\Services\Traffic\VisitorLink;
use Framework\Core\Request;
use Framework\Database;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';
const READER_IP = '203.0.113.77';

beforeEach(function () {
    $config = require ROOT_PATH.'/config/traffic.php';
    $blogs = new BlogModel($this->db);
    $posts = new PostModel($this->db);

    $this->identity = new VisitorIdentity(new TrafficSaltModel($this->db), 'test-key');
    $this->recorder = new TrafficRecorder(
        new TrafficSettings(new SettingModel($this->db)),
        new PagePathResolver($blogs, new BlogSettingsModel($this->db), $posts, new CategoryModel($this->db), new TagModel($this->db), LocaleRegistry::instance()),
        new UserAgentClassifier($config['bot_patterns']),
        new ReferrerClassifier($config['sources'], $config['spam_referrers'], $config['email_mediums']),
        $this->identity,
        new CountryLookup(ROOT_PATH.'/storage/geo/does-not-exist.mmdb'),
        new TrafficHitModel($this->db),
        $blogs,
        $config,
    );

    $this->ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogId = BlogFactory::new($blogs)->published()->create($this->ownerId);
    $this->slug = (string) $this->db->query('SELECT blog_slug FROM blogs WHERE id = ?', [$this->blogId])->fetchColumn();

    $this->blogSettings = new BlogSettingsModel($this->db);
    $this->blogSettings->createDefaultForBlog($this->blogId, []);
    $this->blogSettings->updateForBlog($this->blogId, ['traffic_enabled' => 1]);

    $this->postId = PostFactory::new($posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->ownerId, 'slug' => 'first-post'])
        ->published()->create();
    PostFactory::new($posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->ownerId, 'slug' => 'unfinished'])
        ->draft()->create();
});

afterEach(function () {
    unset($_COOKIE['app_consent']);
});

/**
 * @param  array<string, string>  $headers
 */
function beacon(array $headers = [], string $ip = READER_IP): Request
{
    $headers = array_change_key_case($headers + ['User-Agent' => BROWSER_UA]);

    return new Request('/traffic/hit', 'POST', [], [], [], [], ['REMOTE_ADDR' => $ip], $headers);
}

function pageView(string $path, string $referrer = ''): BeaconPayload
{
    return BeaconPayload::view(json_encode(['v' => bin2hex(random_bytes(16)), 'p' => $path, 'r' => $referrer]));
}

test('a reader view is stored without the IP address or the user agent', function () {
    $outcome = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}/first-post", 'https://www.google.com/search?q=secret'), null, false, null);

    $row = $this->db->query('SELECT * FROM traffic_hits')->fetch(PDO::FETCH_ASSOC);
    $everything = implode('|', array_map('strval', $row));

    expect($outcome)->toBe(TrafficRecorder::RECORDED)
        ->and((int) $row['blog_id'])->toBe($this->blogId)
        ->and((int) $row['post_id'])->toBe($this->postId)
        ->and($row['visitor_kind'])->toBe('daily')
        ->and($row['referrer_host'])->toBe('google.com')
        ->and($everything)->not->toContain(READER_IP)
        ->and($everything)->not->toContain('Chrome/129')
        ->and($everything)->not->toContain('secret');
});

test('the anonymous hash changes with the day, and cannot be rebuilt once the salt is gone', function () {
    $today = $this->identity->daily(READER_IP, BROWSER_UA, '2026-03-01');

    expect($this->identity->daily(READER_IP, BROWSER_UA, '2026-03-01'))->toBe($today)
        ->and($this->identity->daily(READER_IP, BROWSER_UA, '2026-03-02'))->not->toBe($today);

    (new TrafficSaltModel($this->db))->deleteBefore('2026-03-02');

    expect($this->identity->daily(READER_IP, BROWSER_UA, '2026-03-01'))->not->toBe($today);
});

test('a signed-in reader is counted by account, the same on any device', function () {
    $reader = ['id' => 4242, 'roles' => ['reader']];

    $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), $reader, false, null);
    $this->recorder->record(beacon(['User-Agent' => 'Mozilla/5.0 (iPhone) Safari/604.1'], '198.51.100.9'), pageView("/en/blog/{$this->slug}/archive"), $reader, false, null);

    $hashes = $this->db->query('SELECT DISTINCT visitor_hash FROM traffic_hits')->fetchAll(PDO::FETCH_COLUMN);

    expect($hashes)->toHaveCount(1)
        ->and($hashes[0])->toBe($this->identity->forAccount(4242));
});

test('noise is ignored before anything is stored', function (array $headers, string $expected) {
    $outcome = $this->recorder->record(beacon($headers), pageView("/en/blog/{$this->slug}/first-post"), null, false, null);

    expect($outcome)->toBe($expected)
        ->and((int) $this->db->query('SELECT COUNT(*) FROM traffic_hits')->fetchColumn())->toBe(0);
})->with([
    'crawler' => [['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'], TrafficRecorder::BOT],
    'no user agent' => [['User-Agent' => ''], TrafficRecorder::BOT],
    'link prefetch' => [['Sec-Purpose' => 'prefetch'], TrafficRecorder::PREFETCH],
    'old prefetch header' => [['Purpose' => 'prefetch'], TrafficRecorder::PREFETCH],
    'global privacy control' => [['Sec-GPC' => '1'], TrafficRecorder::OPTED_OUT],
    'do not track' => [['DNT' => '1'], TrafficRecorder::OPTED_OUT],
]);

test('only live public pages count', function (string $path) {
    $outcome = $this->recorder->record(beacon(), pageView(str_replace('{slug}', $this->slug, $path)), null, false, null);

    expect($outcome)->toBe(TrafficRecorder::UNKNOWN_PAGE);
})->with([
    'draft post preview' => ['/en/blog/{slug}/unfinished'],
    'missing post' => ['/en/blog/{slug}/no-such-post'],
    'unknown blog' => ['/en/blog/nobody-here'],
    'not a blog page' => ['/en/about'],
    'unknown category' => ['/en/blog/{slug}/category/nothing'],
]);

test('the blog team and administrators are left out', function () {
    $team = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), ['id' => $this->ownerId, 'roles' => ['reader']], false, null);
    $admin = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), ['id' => 999999, 'roles' => ['administrator']], false, null);
    $actingAs = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), ['id' => 777, 'roles' => ['reader']], true, null);

    expect([$team, $admin, $actingAs])->each->toBe(TrafficRecorder::MEMBER);
});

test('the owner can choose to count their team and to exclude paths', function () {
    $this->blogSettings->updateForBlog($this->blogId, [
        'traffic_exclude_members' => 0,
        'traffic_excluded_paths' => "/archive\n/tag",
    ]);

    $team = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), ['id' => $this->ownerId, 'roles' => ['reader']], false, null);
    $archive = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}/archive"), null, false, null);

    expect($team)->toBe(TrafficRecorder::RECORDED)
        ->and($archive)->toBe(TrafficRecorder::EXCLUDED_PATH);
});

test('reloading a page inside the dedupe window counts once', function () {
    $path = "/en/blog/{$this->slug}/first-post";

    $first = $this->recorder->record(beacon(), pageView($path), null, false, null);
    $reload = $this->recorder->record(beacon(), pageView($path), null, false, null);
    $elsewhere = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), null, false, null);
    $someoneElse = $this->recorder->record(beacon([], '198.51.100.20'), pageView($path), null, false, null);

    expect([$first, $reload, $elsewhere, $someoneElse])->toBe([
        TrafficRecorder::RECORDED, TrafficRecorder::DUPLICATE, TrafficRecorder::RECORDED, TrafficRecorder::RECORDED,
    ]);
});

test('a replayed view id is stored once', function () {
    $payload = pageView("/en/blog/{$this->slug}/first-post");

    $this->recorder->record(beacon(), $payload, null, false, null);
    $replay = $this->recorder->record(beacon([], '198.51.100.30'), $payload, null, false, null);

    expect($replay)->toBe(TrafficRecorder::DUPLICATE)
        ->and((int) $this->db->query('SELECT COUNT(*) FROM traffic_hits')->fetchColumn())->toBe(1);
});

test('referrer spam never reaches the table', function () {
    $outcome = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}", 'https://semalt.com/'), null, false, null);

    expect($outcome)->toBe(TrafficRecorder::SPAM);
});

test('a blog counts nothing until its owner turns counting on', function () {
    $otherBlog = BlogFactory::new(new BlogModel($this->db))->published()->create($this->ownerId);
    $otherSlug = (string) $this->db->query('SELECT blog_slug FROM blogs WHERE id = ?', [$otherBlog])->fetchColumn();
    $this->blogSettings->createDefaultForBlog($otherBlog, []);

    $fresh = $this->recorder->record(beacon(), pageView("/en/blog/{$otherSlug}"), null, false, null);

    $this->blogSettings->updateForBlog($this->blogId, ['traffic_enabled' => 0]);
    $switchedOff = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), null, false, null);

    expect([$fresh, $switchedOff])->each->toBe(TrafficRecorder::BLOG_OFF)
        ->and((int) $this->db->query('SELECT COUNT(*) FROM traffic_hits')->fetchColumn())->toBe(0);
});

test('the kill switch stops recording', function () {
    (new SettingModel($this->db))->set('traffic.enabled', '0');

    expect($this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), null, false, null))->toBe(TrafficRecorder::DISABLED);
});

/**
 * @param  bool  $analytics  Whether the reader accepted analytics
 */
function visitorLink(Database $db, VisitorIdentity $identity, bool $analytics): VisitorLink
{
    $config = require ROOT_PATH.'/config/consent.php';
    $store = new ConsentCookieStore($config['cookie_name'], 1, 'test-secret');
    $_COOKIE[$config['cookie_name']] = $analytics
        ? $store->encodeCookieValue(new Consent((int) $config['version'], time(), ['necessary' => true, 'analytics' => true]))
        : null;

    return new VisitorLink($identity, new VisitorCookie('lx_vid', 1), new ConsentService($store, $config), new TrafficHitModel($db), 30);
}

function withCookie(string $cookieId): Request
{
    return new Request('/login', 'POST', [], [], [], ['lx_vid' => $cookieId], ['REMOTE_ADDR' => READER_IP], ['user-agent' => BROWSER_UA]);
}

test('a guest who accepts analytics mid-visit stays one visitor', function () {
    $path = "/en/blog/{$this->slug}/first-post";
    $cookieId = bin2hex(random_bytes(16));
    $hits = new TrafficHitModel($this->db);

    $this->recorder->record(beacon(), pageView($path), null, false, null);
    $moved = visitorLink($this->db, $this->identity, true)->toCookie(beacon(), $cookieId);
    $refresh = $this->recorder->record(beacon(), pageView($path), null, false, $cookieId);

    expect($moved)->toBe(1)
        ->and($refresh)->toBe(TrafficRecorder::DUPLICATE)
        ->and($hits->countRecent($this->blogId, 30))->toBe(1)
        ->and($this->db->query('SELECT visitor_kind FROM traffic_hits')->fetchColumn())->toBe('cookie');
});

test('someone else behind the same connection stays a separate visitor after the link', function () {
    $cookieId = bin2hex(random_bytes(16));

    $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), null, false, null);
    visitorLink($this->db, $this->identity, true)->toCookie(beacon(), $cookieId);
    $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}/first-post"), null, false, $cookieId);
    $neighbour = $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}/first-post"), null, false, null);

    expect($neighbour)->toBe(TrafficRecorder::RECORDED)
        ->and((new TrafficHitModel($this->db))->countRecent($this->blogId, 30))->toBe(2);
});

test('a reader who turns analytics off mid-visit stays one visitor', function () {
    $path = "/en/blog/{$this->slug}/first-post";
    $cookieId = bin2hex(random_bytes(16));

    $this->recorder->record(beacon(), pageView($path), null, false, $cookieId);
    visitorLink($this->db, $this->identity, false)->dropCookie(withCookie($cookieId));
    $refresh = $this->recorder->record(beacon(), pageView($path), null, false, null);

    expect($refresh)->toBe(TrafficRecorder::DUPLICATE)
        ->and((new TrafficHitModel($this->db))->countRecent($this->blogId, 30))->toBe(1)
        ->and($this->db->query('SELECT visitor_kind FROM traffic_hits')->fetchColumn())->toBe('daily');
});

test('signing in joins the guest visit to the account only with analytics consent', function () {
    $cookieId = bin2hex(random_bytes(16));
    $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), null, false, null);
    $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}/first-post"), null, false, $cookieId);

    $declined = visitorLink($this->db, $this->identity, false)->toAccount(withCookie($cookieId), 4242);
    $accepted = visitorLink($this->db, $this->identity, true)->toAccount(withCookie($cookieId), 4242);

    $kinds = $this->db->query('SELECT DISTINCT visitor_kind FROM traffic_hits')->fetchAll(PDO::FETCH_COLUMN);

    expect($declined)->toBe(0)
        ->and($accepted)->toBe(2)
        ->and($kinds)->toBe(['account'])
        ->and((new TrafficHitModel($this->db))->countRecent($this->blogId, 30))->toBe(1);
});
