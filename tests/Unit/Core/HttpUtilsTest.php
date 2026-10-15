<?php

declare(strict_types=1);

/**
 * HttpUtils::isHttps is the one HTTPS check behind the redirector, HSTS,
 * Request::isSecure and every secure-cookie flag, so these cases pin the
 * rule that forwarded headers count only from a TRUSTED_PROXIES peer.
 */

use Framework\HttpUtils;
use Tests\Helpers\EnvironmentTestHelper;

beforeEach(function () {
    EnvironmentTestHelper::setEnv('TRUSTED_PROXIES', '10.0.0.0/8');
});

afterEach(function () {
    EnvironmentTestHelper::restore();
});

it('decides HTTPS from server flags and trusted proxy headers', function (array $server, bool $expected) {
    expect(HttpUtils::isHttps($server))->toBe($expected);
})->with([
    'plain http' => [['REMOTE_ADDR' => '203.0.113.7'], false],
    'HTTPS on' => [['HTTPS' => 'on'], true],
    'HTTPS off' => [['HTTPS' => 'off'], false],
    'port 443' => [['SERVER_PORT' => '443'], true],
    'request scheme' => [['REQUEST_SCHEME' => 'https'], true],
    'proto from trusted proxy' => [['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_PROTO' => 'https'], true],
    'ssl from trusted proxy' => [['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_SSL' => 'on'], true],
    'proto chain, first entry wins' => [['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_PROTO' => 'https, http'], true],
    'proto http from trusted proxy' => [['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_PROTO' => 'http, https'], false],
    'proto from untrusted client' => [['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_PROTO' => 'https'], false],
    'ssl from untrusted client' => [['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_SSL' => 'on'], false],
    'proto with no peer' => [['HTTP_X_FORWARDED_PROTO' => 'https'], false],
]);

it('ignores forwarded headers when no proxies are configured', function () {
    EnvironmentTestHelper::setEnv('TRUSTED_PROXIES', '');

    expect(HttpUtils::isHttps(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_PROTO' => 'https']))->toBeFalse();
});
