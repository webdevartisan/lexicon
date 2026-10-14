<?php

declare(strict_types=1);

/**
 * Component names are lowercased before lookup, so a file with capitals only
 * resolves on case-insensitive filesystems and 500s on Linux.
 */
it('keeps every component file name lowercase', function () {
    $root = dirname(__DIR__, 3).'/views/components';

    foreach (glob($root.'/*.lex.php') as $file) {
        expect(basename($file))->toBe(strtolower(basename($file)));
    }
});

it('points every cmp tag at an existing component file', function () {
    $root = dirname(__DIR__, 3).'/views';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (!str_ends_with($file->getFilename(), '.lex.php')) {
            continue;
        }

        preg_match_all('/\{%\s*cmp\s*=\s*["\']([^"\']+)["\']/', file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $name) {
            $expected = $root.'/components/'.strtolower(trim($name)).'.lex.php';
            expect(is_file($expected))->toBeTrue("{$file->getPathname()} uses missing component '{$name}'");
        }
    }
});
