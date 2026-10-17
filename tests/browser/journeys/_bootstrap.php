<?php

declare(strict_types=1);

// Failure files from earlier runs would look like this run's
foreach (glob(codecept_output_dir('*.fail.{png,html}'), GLOB_BRACE) ?: [] as $stale) {
    unlink($stale);
}

// Every run signs up new accounts, and sign-ups are limited per address
$php = getenv('WALK_PHP') ?: PHP_BINARY;
exec(escapeshellarg($php).' '.escapeshellarg(dirname(__DIR__, 3).'/cli').' ratelimit:clear 127.0.0.1 ::1', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "Could not clear the sign-up limit; sign-ups may be refused.\n");
}
