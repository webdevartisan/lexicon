<?php

declare(strict_types=1);

test('bootstrap forces PHP to UTC even when php.ini says otherwise', function () {
    $root = dirname(__DIR__, 2);
    $script = tempnam(sys_get_temp_dir(), 'tz');
    file_put_contents($script, "<?php require '{$root}/bootstrap/constants.php'; require '{$root}/bootstrap/app.php'; echo date_default_timezone_get();");

    $output = shell_exec(escapeshellarg(PHP_BINARY).' -d date.timezone=Europe/Athens '.escapeshellarg($script));
    unlink($script);

    expect(trim((string) $output))->toBe('UTC');
});
