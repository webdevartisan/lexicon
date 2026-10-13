<?php

declare(strict_types=1);

use Framework\Core\Container;
use Framework\Session\Drivers\FileSessionDriver;
use Framework\Session\SessionDriver;
use Framework\Session\SessionManager;
use Tests\Helpers\SessionTestHelper;

/**
 * Driver selection by name, the way SESSION_DRIVER picks one.
 */
test('a misspelt driver fails with a message that says how to fix it', function () {
    $manager = new SessionManager(new Container(), SessionTestHelper::config(['driver' => 'databse']));

    expect(fn () => $manager->assertSupported())
        ->toThrow(InvalidArgumentException::class, 'Session driver [databse] is not supported. Set SESSION_DRIVER to file, database or redis');
});

test('the file driver needs no connection', function () {
    $manager = new SessionManager(new Container(), SessionTestHelper::config(['driver' => 'file']));

    expect($manager->driver())->toBeInstanceOf(FileSessionDriver::class)
        ->and($manager->driver()->storesRecords())->toBeFalse();
});

test('a driver registered with extend() is used by name', function () {
    $custom = Mockery::mock(SessionDriver::class);
    $manager = (new SessionManager(new Container(), SessionTestHelper::config(['driver' => 'memory'])))
        ->extend('memory', fn () => $custom);

    $manager->assertSupported();

    expect($manager->driver())->toBe($custom);
});

test('stored data outlives the longest idle limit of any kind of session', function () {
    $manager = new SessionManager(new Container(), SessionTestHelper::config([
        'idle_minutes' => 480,
        'privileged_idle_minutes' => 60,
        'guest_idle_minutes' => 120,
    ]));

    expect($manager->longestIdleSeconds())->toBe(480 * 60);
});
