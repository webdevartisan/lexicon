<?php

declare(strict_types=1);

echo "\n Running unit tests (fast, mocked)...\n\n";

$path = $argv[1] ?? 'tests/Unit';
$parallel = in_array('--no-parallel', $argv, true) ? '' : '--parallel';

// Use PHP to execute Pest (cross-platform)
$command = sprintf('php vendor/bin/pest %s %s', escapeshellarg($path), $parallel);

passthru($command, $exitCode);
exit($exitCode);
