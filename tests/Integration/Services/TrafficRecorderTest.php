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

test('a signed-in reader who allowed analytics is counted by account, the same on any device', function () {
    $reader = ['id' => 4242, 'roles' => ['reader']];

    $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), $reader, false, 'laptop-cookie');
    $this->recorder->record(beacon(['User-Agent' => 'Mozilla/5.0 (iPhone) Safari/604.1'], '198.51.100.9'), pageView("/en/blog/{$this->slug}/archive"), $reader, false, 'phone-cookie');

    $hashes = $this->db->query('SELECT DISTINCT visitor_hash FROM traffic_hits')->fetchAll(PDO::FETCH_COLUMN);

    expect($hashes)->toHaveCount(1)
        ->and($hashes[0])->toBe($this->identity->forAccount(4242));
});

test('a signed-in reader who did not allow analytics is told apart for the day only', function () {
    $this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), ['id' => 4242, 'roles' => ['reader']], false, null);

    $row = $this->db->query('SELECT visitor_hash, visitor_kind FROM traffic_hits')->fetch(PDO::FETCH_ASSOC);

    expect($row['visitor_kind'])->toBe('daily')
        ->and($row['visitor_hash'])->not->toBe($this->identity->forAccount(4242));
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
    'unknown category' => ['/en/blog/{slug}/category/nothing'],
    'an account page' => ['/en/account/profile'],
    'a link carrying a token' => ['/en/password/reset/0123456789abcdef'],
    'a guide that does not exist' => ['/en/getting-started/no-such-guide'],
    'a dashboard page' => ['/en/dashboard'],
]);

test("the platform's own pages count, with no blog and a UTC day", function (string $path, string $type, string $stored) {
    $outcome = $this->recorder->record(beacon(), pageView($path), null, false, null);
    $row = $this->db->query('SELECT blog_id, post_id, page_type, path, local_date FROM traffic_hits')->fetch(PDO::FETCH_ASSOC);

    expect($outcome)->toBe(TrafficRecorder::RECORDED)
        ->and($row['blog_id'])->toBeNull()
        ->and($row['post_id'])->toBeNull()
        ->and($row['page_type'])->toBe($type)
        ->and($row['path'])->toBe($stored)
        ->and($row['local_date'])->toBe(gmdate('Y-m-d'));
})->with([
    'home page' => ['/en', 'home', '/'],
    'home page in Greek' => ['/el/', 'home', '/'],
    'discover, search dropped' => ['/en/discover?q=gardening', 'discover', '/discover'],
    'about' => ['/en/about', 'static_page', '/about'],
    'a guide' => ['/en/getting-started/start-your-first-blog', 'guide', '/getting-started/start-your-first-blog'],
    'a profile, without its handle' => ['/en/profile/someone', 'profile', '/profile'],
    'sign-up' => ['/ar/register', 'auth', '/register'],
]);

test("on the platform's pages only administrators are left out, not a blog's team", function () {
    $team = $this->recorder->record(beacon(), pageView('/en/about'), ['id' => $this->ownerId, 'roles' => ['reader']], false, null);
    $admin = $this->recorder->record(beacon(), pageView('/en/about'), ['id' => 999999, 'roles' => ['administrator']], false, null);

    expect($team)->toBe(TrafficRecorder::RECORDED)
        ->and($admin)->toBe(TrafficRecorder::MEMBER);
});

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
    $home = $this->recorder->record(beacon(), pageView('/en'), null, false, null);
    $homeAgainInGreek = $this->recorder->record(beacon(), pageView('/el'), null, false, null);

    expect([$first, $reload, $elsewhere, $someoneElse, $home, $homeAgainInGreek])->toBe([
        TrafficRecorder::RECORDED, TrafficRecorder::DUPLICATE, TrafficRecorder::RECORDED, TrafficRecorder::RECORDED,
        TrafficRecorder::RECORDED, TrafficRecorder::DUPLICATE,
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

test('a reader from another part of Lexicon keeps the blog or kind of page they came from, never its path', function (string $page, string $from, string $channel, ?string $source) {
    $blogs = new BlogModel($this->db);
    $other = BlogFactory::new($blogs)->published()->create($this->ownerId);
    $hidden = BlogFactory::new($blogs)->draft()->create($this->ownerId);
    $slugs = $this->db->query('SELECT id, blog_slug FROM blogs')->fetchAll(PDO::FETCH_KEY_PAIR);
    $fill = fn (string $text): string => strtr($text, [
        '{slug}' => $slugs[$this->blogId], '{other}' => $slugs[$other], '{hidden}' => $slugs[$hidden],
        '{blogId}' => (string) $this->blogId, '{otherId}' => (string) $other,
    ]);

    $outcome = $this->recorder->record(beacon(), pageView($fill($page), base_url().$fill($from)), null, false, null);
    $row = $this->db->query('SELECT channel, referrer_host, referrer_source FROM traffic_hits')->fetch(PDO::FETCH_ASSOC);

    expect($outcome)->toBe(TrafficRecorder::RECORDED)
        ->and($row['channel'])->toBe($channel)
        ->and($row['referrer_host'])->toBeNull()
        ->and($row['referrer_source'])->toBe($source === null ? null : $fill($source));
})->with([
    'within the blog' => ['/en/blog/{slug}/first-post', '/el/blog/{slug}', 'internal', null],
    'from Discover' => ['/en/blog/{slug}', '/el/discover', 'lexicon', 'discover'],
    'from the home page' => ['/en/blog/{slug}', '/en', 'lexicon', 'home'],
    'from a profile' => ['/en/blog/{slug}', '/en/profile/someone', 'lexicon', 'profile'],
    'from another blog' => ['/en/blog/{slug}', '/en/blog/{other}/a-post', 'lexicon', 'blog:{otherId}'],
    'from a blog that is not public' => ['/en/blog/{slug}', '/en/blog/{hidden}', 'lexicon', 'other'],
    'from the dashboard' => ['/en/blog/{slug}', '/en/dashboard', 'lexicon', 'other'],
    'between platform pages' => ['/en/about', '/en/discover', 'internal', null],
    'from an account page to the platform' => ['/en/discover', '/en/account/notifications', 'internal', null],
    'from a blog to sign-up' => ['/en/register', '/en/blog/{slug}/first-post', 'lexicon', 'blog:{blogId}'],
]);

test('a new blog is counted from its first reader, with nothing for its owner to switch on', function () {
    $otherBlog = BlogFactory::new(new BlogModel($this->db))->published()->create($this->ownerId);
    $otherSlug = (string) $this->db->query('SELECT blog_slug FROM blogs WHERE id = ?', [$otherBlog])->fetchColumn();
    $this->blogSettings->createDefaultForBlog($otherBlog, []);

    expect($this->recorder->record(beacon(), pageView("/en/blog/{$otherSlug}"), null, false, null))->toBe(TrafficRecorder::RECORDED);
});

test('the platform switch stops recording everywhere', function () {
    (new SettingModel($this->db))->set('traffic.enabled', '0');

    expect($this->recorder->record(beacon(), pageView("/en/blog/{$this->slug}"), null, false, null))->toBe(TrafficRecorder::DISABLED)
        ->and($this->recorder->record(beacon(), pageView('/en/about'), null, false, null))->toBe(TrafficRecorder::DISABLED);
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

test('a signed-in reader who turns analytics off mid-visit stays one visitor', function () {
    $reader = ['id' => 4242, 'roles' => ['reader']];
    $cookieId = bin2hex(random_bytes(16));
    $path = "/en/blog/{$this->slug}/first-post";

    $this->recorder->record(beacon(), pageView($path), $reader, false, $cookieId);
    visitorLink($this->db, $this->identity, false)->dropCookie(withCookie($cookieId), 4242);
    $refresh = $this->recorder->record(beacon(), pageView($path), $reader, false, null);

    expect($refresh)->toBe(TrafficRecorder::DUPLICATE)
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
