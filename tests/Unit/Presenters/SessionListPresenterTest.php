<?php

declare(strict_types=1);

use App\Presenters\SessionListPresenter;

/**
 * How one session row reads in the list, for the owner and for an admin.
 */
beforeEach(function () {
    $this->presenter = new SessionListPresenter(
        static fn (string $key, array $params = []): string => $key.($params === [] ? '' : ':'.implode(',', $params)),
        'en',
        'UTC'
    );

    $this->row = static fn (array $columns = []): array => array_replace([
        'id' => hash('sha256', bin2hex(random_bytes(8))),
        'impersonated_by' => null,
        'ip_address' => '203.0.113.9',
        'device' => 'desktop',
        'browser' => 'Firefox',
        'browser_version' => '131',
        'os' => 'Windows',
        'os_version' => null,
        'country' => 'GR',
        'region' => null,
        'city' => 'Patras',
        'created_at' => time() - 7200,
        'signed_in_at' => time() - 7200,
        'last_activity' => time() - 3600,
    ], $columns);
});

test('a row names the browser, the system and the place', function () {
    [$item] = $this->presenter->present([($this->row)()], '');

    expect($item['client'])->toBe('account.sessions.clientOn:Firefox 131,Windows')
        ->and($item['place'])->toBe('Patras, Greece')
        ->and($item['active_now'])->toBeFalse()
        ->and($item['current'])->toBeFalse();
});

test('this device comes first and is marked', function () {
    $other = ($this->row)(['last_activity' => time()]);
    $mine = ($this->row)();

    $items = $this->presenter->present([$other, $mine], $mine['id']);

    expect($items[0]['hash'])->toBe($mine['id'])
        ->and($items[0]['current'])->toBeTrue()
        ->and($items[1]['active_now'])->toBeTrue();
});

test('a private address reads as the local network', function () {
    [$item] = $this->presenter->present([($this->row)(['ip_address' => '192.168.1.20', 'country' => null, 'city' => null])], '');

    expect($item['place'])->toBe('account.sessions.localNetwork');
});

test('an unrecognised client and an unknown device type fall back plainly', function () {
    [$item] = $this->presenter->present([($this->row)(['browser' => 'Other', 'os' => null, 'device' => 'bot'])], '');

    expect($item['client'])->toBe('account.sessions.unknownDevice')
        ->and($item['device'])->toBe('other');
});

test('administrators see the network, not the address', function () {
    [$v4] = $this->presenter->present([($this->row)()], '', true);
    [$v6] = $this->presenter->present([($this->row)(['ip_address' => '2001:db8:85a3:1:2:3:4:5'])], '', true);

    expect($v4['ip'])->toBe('203.0.113.0/24')
        ->and($v6['ip'])->toBe('2001:db8:85a3::/48');
});
