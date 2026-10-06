<?php

declare(strict_types=1);

use App\Services\Analytics\UserAgentClassifier;

function agents(): UserAgentClassifier
{
    return new UserAgentClassifier((require ROOT_PATH.'/config/analytics.php')['bot_patterns']);
}

test('crawlers, tools and headless browsers are not counted', function (string $ua) {
    expect(agents()->isBot($ua))->toBeTrue();
})->with([
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0 Safari/537.36',
    'curl/8.4.0',
    'python-requests/2.31.0',
    'Mozilla/5.0 (Macintosh) Chrome-Lighthouse',
    '',
]);

test('ordinary browsers are counted', function (string $ua) {
    expect(agents()->isBot($ua))->toBeFalse();
})->with([
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0',
]);

test('patterns an administrator adds are honoured', function () {
    expect(agents()->isBot('Mozilla/5.0 CompanyMonitor-Probe', ['companymonitor']))->toBeTrue();
});

test('user agents reduce to families', function (string $ua, array $expected) {
    expect(agents()->classify($ua))->toBe($expected);
})->with([
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36 Edg/129.0',
        ['device' => 'desktop', 'browser' => 'Edge', 'os' => 'Windows']],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        ['device' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS']],
    ['Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
        ['device' => 'tablet', 'browser' => 'Chrome', 'os' => 'Android']],
    ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0 Mobile Safari/537.36',
        ['device' => 'mobile', 'browser' => 'Samsung Internet', 'os' => 'Android']],
]);
