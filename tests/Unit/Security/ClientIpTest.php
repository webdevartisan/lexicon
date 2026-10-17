<?php

declare(strict_types=1);

use Framework\Security\ClientIp;
use Framework\Security\IpRange;

/**
 * Unit tests for trusted-proxy client IP resolution and range matching.
 */
function ipServer(string $peer, ?string $forwarded = null): array
{
    $server = ['REMOTE_ADDR' => $peer];
    if ($forwarded !== null) {
        $server['HTTP_X_FORWARDED_FOR'] = $forwarded;
    }

    return $server;
}

test('forwarded header is ignored when no proxies are trusted', function () {
    expect(ClientIp::resolve(ipServer('203.0.113.9', '1.2.3.4'), []))->toBe('203.0.113.9');
});

test('forwarded header is ignored when the peer is not a trusted proxy', function () {
    expect(ClientIp::resolve(ipServer('203.0.113.9', '1.2.3.4'), ['10.0.0.0/8']))->toBe('203.0.113.9');
});

test('trusted peer yields the forwarded client', function () {
    expect(ClientIp::resolve(ipServer('10.0.0.5', '198.51.100.7'), ['10.0.0.0/8']))->toBe('198.51.100.7');
});

test('spoofed left-hand entries are skipped', function () {
    $server = ipServer('10.0.0.5', '6.6.6.6, 198.51.100.7');

    expect(ClientIp::resolve($server, ['10.0.0.0/8']))->toBe('198.51.100.7');
});

test('chain of trusted proxies is walked from the right', function () {
    $server = ipServer('10.0.0.5', '198.51.100.7, 10.0.0.9, 10.0.0.8');

    expect(ClientIp::resolve($server, ['10.0.0.0/8']))->toBe('198.51.100.7');
});

test('malformed entry stops the walk at the last vouched hop', function () {
    $server = ipServer('10.0.0.5', '198.51.100.7, not-an-ip, 10.0.0.9');

    expect(ClientIp::resolve($server, ['10.0.0.0/8']))->toBe('10.0.0.9');
});

test('trusted peer without a header resolves to the peer', function () {
    expect(ClientIp::resolve(ipServer('127.0.0.1'), ['127.0.0.1']))->toBe('127.0.0.1');
});

test('missing peer address resolves to null', function () {
    expect(ClientIp::resolve([], ['127.0.0.1']))->toBeNull();
});

test('ipv6 clients behind an ipv4 proxy resolve correctly', function () {
    $server = ipServer('127.0.0.1', '2001:db8::1');

    expect(ClientIp::resolve($server, ['127.0.0.1']))->toBe('2001:db8::1');
});

test('overlong chains are capped', function () {
    $server = ipServer('10.0.0.5', '1.1.1.1'.str_repeat(', 10.0.0.9', 50));

    expect(ClientIp::resolve($server, ['10.0.0.0/8']))->toBe('10.0.0.9');
});

test('request ip honours TRUSTED_PROXIES from the environment', function () {
    $previous = $_ENV['TRUSTED_PROXIES'] ?? null;
    $request = new Framework\Core\Request('/', 'GET', [], [], [], [], ipServer('127.0.0.1', '198.51.100.7'), []);

    try {
        unset($_ENV['TRUSTED_PROXIES']);
        expect($request->ip())->toBe('127.0.0.1');

        $_ENV['TRUSTED_PROXIES'] = '127.0.0.1,::1';
        expect($request->ip())->toBe('198.51.100.7');
    } finally {
        if ($previous === null) {
            unset($_ENV['TRUSTED_PROXIES']);
        } else {
            $_ENV['TRUSTED_PROXIES'] = $previous;
        }
    }
});

test('parseList drops wildcards, match-all ranges and junk', function () {
    $list = ClientIp::parseList('127.0.0.1, *, 0.0.0.0/0, ::/0, 10.0.0.0/8, 10.0.0.0/33, nope, fd00::/8');

    expect($list)->toBe(['127.0.0.1', '10.0.0.0/8', 'fd00::/8']);
});

test('ranges match on bit boundaries for ipv4', function () {
    expect(IpRange::contains('192.168.1.200', '192.168.1.128/25'))->toBeTrue()
        ->and(IpRange::contains('192.168.1.127', '192.168.1.128/25'))->toBeFalse()
        ->and(IpRange::contains('10.1.2.3', '10.0.0.0/8'))->toBeTrue()
        ->and(IpRange::contains('11.0.0.1', '10.0.0.0/8'))->toBeFalse();
});

test('ranges match for ipv6 and never across families', function () {
    expect(IpRange::contains('fd12::1', 'fd00::/8'))->toBeTrue()
        ->and(IpRange::contains('fe80::1', 'fd00::/8'))->toBeFalse()
        ->and(IpRange::contains('10.0.0.1', 'fd00::/8'))->toBeFalse()
        ->and(IpRange::contains('::1', '127.0.0.1'))->toBeFalse();
});

test('ipv4-mapped ipv6 peers match ipv4 ranges', function () {
    expect(IpRange::contains('::ffff:10.0.0.5', '10.0.0.0/8'))->toBeTrue();
});
