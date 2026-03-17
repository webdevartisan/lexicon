<?php

declare(strict_types=1);

echo "\n Running integration tests (database)...\n\n";

$path = $argv[1] ?? 'tests/Integration';
$parallel = in_array('--no-parallel', $argv, true) ? '' : '';

// Integration tests shouldn't run in parallel by default (database transactions)
$command = sprintf('php vendor/bin/pest %s %s', escapeshellarg($path), $parallel);

passthru($command, $exitCode);
exit($exitCode);
