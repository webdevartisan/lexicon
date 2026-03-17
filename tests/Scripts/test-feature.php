<?php

declare(strict_types=1);

echo "\n Running feature tests (full stack)...\n\n";

$path = $argv[1] ?? 'tests/Feature';
$parallel = in_array('--no-parallel', $argv, true) ? '' : '';

// Feature tests shouldn't run in parallel by default (database + HTTP state)
$command = sprintf('php vendor/bin/pest %s %s', escapeshellarg($path), $parallel);

passthru($command, $exitCode);
exit($exitCode);
