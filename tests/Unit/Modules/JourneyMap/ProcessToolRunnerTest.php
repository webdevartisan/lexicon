<?php

declare(strict_types=1);

use Modules\JourneyMap\ProcessToolRunner;
use Tests\Helpers\ModuleFixtures;

/**
 * The runner starts a program from an argument list, passes on what it prints and stops it when asked.
 * PHP itself plays the program, so the test runs on any system.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->runner = new ProcessToolRunner();
});

test('lines from both outputs reach the listener, split on carriage returns too', function () {
    $lines = [];
    $code = $this->runner->run(
        [PHP_BINARY, '-r', 'fwrite(STDERR, "10%\r55%\n"); echo "done\n"; exit(3);'],
        function (string $line) use (&$lines): void {
            $lines[] = $line;
        },
        fn (): bool => false
    );

    sort($lines);
    expect($code)->toBe(3)
        ->and($lines)->toBe(['10%', '55%', 'done']);
});

test('arguments reach the program as they are, with no shell in between', function () {
    $lines = [];
    $this->runner->run(
        [PHP_BINARY, '-r', 'echo $argv[1], "\n";', 'two words & more'],
        function (string $line) use (&$lines): void {
            $lines[] = $line;
        },
        fn (): bool => false
    );

    expect($lines)->toBe(['two words & more']);
});

test('a stop request ends the program', function () {
    $started = microtime(true);
    $code = $this->runner->run(
        [PHP_BINARY, '-r', 'sleep(30);'],
        fn (string $line) => null,
        fn (): bool => microtime(true) - $started > 0.5
    );

    expect($code)->toBe(130)
        ->and(microtime(true) - $started)->toBeLessThan(5);
});
