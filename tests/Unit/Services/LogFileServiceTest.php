<?php

declare(strict_types=1);

use App\Services\LogFileService;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lexicon-logs-'.uniqid();
    mkdir($this->dir);
    file_put_contents($this->dir.'/cron.log', "Started 2 task(s).\nStarted 1 task(s).\n");
    file_put_contents($this->dir.'/csp-violations.log', "violation\n");
    file_put_contents($this->dir.'/notes.txt', 'not a log');

    $this->logs = new LogFileService($this->dir);
});

afterEach(function () {
    array_map('unlink', glob($this->dir.'/*') ?: []);
    rmdir($this->dir);
});

test('the listing includes cron.log beside the other logs and nothing else', function () {
    expect(array_keys($this->logs->all()))->toEqualCanonicalizing(['cron.log', 'csp-violations.log']);
});

test('clearing a log empties it and keeps the file', function () {
    $bytes = $this->logs->clear('cron.log');

    clearstatcache();
    expect($bytes)->toBe(38)
        ->and(is_file($this->dir.'/cron.log'))->toBeTrue()
        ->and(filesize($this->dir.'/cron.log'))->toBe(0)
        ->and(filesize($this->dir.'/csp-violations.log'))->toBeGreaterThan(0);
});

test('a log keeps accepting appends after it is cleared', function () {
    $this->logs->clear('cron.log');
    file_put_contents($this->dir.'/cron.log', "Started 3 task(s).\n", FILE_APPEND);

    expect($this->logs->tail('cron.log', 10))->toBe('Started 3 task(s).');
});

test('names that are not listed logs are refused', function (string $name) {
    expect(fn () => $this->logs->clear($name))->toThrow(InvalidArgumentException::class);
})->with(['../../.env', 'notes.txt', 'missing.log', '']);

test('the tail returns only the last lines', function () {
    file_put_contents($this->dir.'/big.log', implode("\n", range(1, 500))."\n");

    expect($this->logs->tail('big.log', 3))->toBe("498\n499\n500");
});
