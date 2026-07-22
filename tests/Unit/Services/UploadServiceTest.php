<?php

declare(strict_types=1);

use App\Services\UploadService;

/**
 * UploadService Unit Test Suite
 *
 * Guards the upload validation gate. Uploads are served same-origin from
 * /uploads, so anything scriptable that gets stored is stored XSS.
 */
beforeEach(function () {
    $this->uploader = new UploadService();

    $this->tmpDir = sys_get_temp_dir().'/lexicon-upload-test-'.bin2hex(random_bytes(4));
    @mkdir($this->tmpDir, 0775, true);

    // Build a $_FILES-shaped entry backed by a real file so finfo can sniff it.
    $this->makeFile = function (string $name, string $contents): array {
        $path = $this->tmpDir.'/'.$name;
        file_put_contents($path, $contents);

        return [
            'name' => $name,
            'tmp_name' => $path,
            'size' => strlen($contents),
            'error' => UPLOAD_ERR_OK,
        ];
    };

    $this->opts = [
        'dir' => $this->tmpDir.'/dest',
        'base_url' => '/uploads/test',
    ];
});

afterEach(function () {
    // Recursive cleanup: the suite shares storage/, so nothing may leak out of tmp.
    if (is_dir($this->tmpDir)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($this->tmpDir);
    }
});

/**
 * An SVG can script without ever containing the string "<script".
 */
test('rejects an SVG that scripts via onload', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.domain)"><rect/></svg>';
    $file = ($this->makeFile)('payload.svg', $svg);

    expect(fn () => $this->uploader->storeImage($file, $this->opts))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * The MIME allow-list is the real gate: allowed_ext is caller-supplied, so a
 * caller re-adding 'svg' must not be able to reopen the hole on its own.
 */
test('rejects an SVG even when the caller allows the svg extension', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body onload="alert(1)"/></foreignObject></svg>';
    $file = ($this->makeFile)('logo.svg', $svg);

    $opts = $this->opts + ['allowed_ext' => ['jpg', 'jpeg', 'png', 'webp', 'svg']];

    expect(fn () => $this->uploader->storeImage($file, $opts))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * Anything whose bytes are not a real raster image is refused regardless of
 * the extension it claims.
 */
test('rejects a file whose contents do not match its extension', function () {
    $file = ($this->makeFile)('notreally.png', '<?php echo "hi"; ?>');

    expect(fn () => $this->uploader->storeImage($file, $this->opts))
        ->toThrow(InvalidArgumentException::class, 'File type not allowed.');
});

/**
 * Regression guard: a genuine PNG must still clear every validation step.
 *
 * storeImage() returns null rather than a URL because move_uploaded_file()
 * refuses a file that did not arrive via a real upload. Reaching that point at
 * all means validation passed.
 */
test('accepts a genuine PNG through validation', function () {
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
    $file = ($this->makeFile)('pixel.png', $png);

    expect($this->uploader->storeImage($file, $this->opts))->toBeNull();
});
