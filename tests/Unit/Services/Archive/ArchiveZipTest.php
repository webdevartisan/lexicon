<?php

declare(strict_types=1);

use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveFormat;
use App\Services\Archive\ArchiveZip;

/**
 * An uploaded archive is hostile until proven otherwise. Everything here must be
 * refused from the zip index alone, before any entry is decompressed.
 */
beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/lexicon-archive-zip-'.bin2hex(random_bytes(4));
    mkdir($this->dir, 0775, true);

    $this->manifest = json_encode(['format' => ArchiveFormat::NAME, 'format_version' => 1]);

    // Entries are written through ZipArchive so names reach the index exactly as given.
    $this->zip = function (array $entries, int $method = ZipArchive::CM_DEFLATE): string {
        $path = $this->dir.'/'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        foreach ($entries as $name => $data) {
            $zip->addFromString($name, $data);
            $zip->setCompressionName($name, $method);
        }
        $zip->close();

        return $path;
    };
});

afterEach(function () {
    foreach (glob($this->dir.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->dir);
});

it('refuses entries that climb out of the archive', function (string $name) {
    $path = ($this->zip)(['manifest.json' => $this->manifest, $name => 'x']);

    expect(fn () => ArchiveZip::open($path))->toThrow(ArchiveException::class, 'unsafe file path');
})->with([
    'parent segment' => '../evil.php',
    'nested parent' => 'media/../../evil.php',
    'absolute' => '/etc/passwd',
    'drive letter' => 'C:/evil.php',
    'backslash' => 'media\\..\\evil.php',
    'dot segment' => 'media/./x.jpg',
]);

it('refuses an archive with no manifest', function () {
    $path = ($this->zip)(['posts.json' => '[]']);

    expect(fn () => ArchiveZip::open($path))->toThrow(ArchiveException::class, 'no manifest.json');
});

it('refuses a file that is not a zip', function () {
    $path = $this->dir.'/fake.zip';
    file_put_contents($path, '%PDF-1.4 not a zip');

    expect(fn () => ArchiveZip::open($path))->toThrow(ArchiveException::class, 'not a readable zip');
});

it('refuses an entry that expands too far for its size', function () {
    $path = ($this->zip)(['manifest.json' => $this->manifest, 'posts.json' => str_repeat(' ', 5 * 1024 * 1024)]);

    expect(fn () => ArchiveZip::open($path))->toThrow(ArchiveException::class, 'expands too far');
});

it('refuses an image entry over the per-file limit', function () {
    $path = ($this->zip)(
        ['manifest.json' => $this->manifest, 'media/big.jpg' => random_bytes(ArchiveFormat::MAX_IMAGE_BYTES + 1)],
        ZipArchive::CM_STORE
    );

    expect(fn () => ArchiveZip::open($path))->toThrow(ArchiveException::class, 'larger than 20 MB');
});

it('reads a sound archive and caps what a JSON entry may produce', function () {
    $path = ($this->zip)(['manifest.json' => $this->manifest, 'posts.json' => json_encode([['title' => 'A']])]);

    $archive = ArchiveZip::open($path);

    expect($archive->json('manifest.json'))->toBe(['format' => ArchiveFormat::NAME, 'format_version' => 1])
        ->and(fn () => $archive->json('posts.json', 5))->toThrow(ArchiveException::class);

    $archive->close();
});

it('sniffs a disguised file by its bytes, not its name', function () {
    $path = ($this->zip)(['manifest.json' => $this->manifest, 'media/fake.jpg' => '<?php echo 1; ?>']);

    $archive = ArchiveZip::open($path);

    expect($archive->sniffMime('media/fake.jpg'))->not->toBe('image/jpeg');

    $archive->close();
});
