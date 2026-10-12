<?php

declare(strict_types=1);

namespace App\Services\Archive;

use RuntimeException;
use ZipArchive;

/**
 * Read-only access to an uploaded archive, after checking it is safe to read at all.
 *
 * Every check here runs on the zip's central directory before any entry is
 * decompressed: the file size, the entry count, every entry name, the declared
 * sizes and compression ratios. The declared sizes can lie, so every read also
 * counts the bytes that actually come out and stops at the cap.
 */
final class ArchiveZip
{
    private const SAFE_NAME = '#^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*/?$#';

    /** @var array<string, int> Entry name => declared uncompressed size */
    private array $sizes = [];

    private function __construct(private ZipArchive $zip) {}

    /**
     * Open and vet an archive.
     *
     * @throws ArchiveException When the archive breaks any limit or rule
     */
    public static function open(string $path): self
    {
        $size = is_file($path) ? (int) filesize($path) : 0;
        if ($size === 0) {
            throw new ArchiveException('The archive is empty or missing.', 'archive.errors.empty');
        }
        if ($size > ArchiveFormat::MAX_ARCHIVE_BYTES) {
            throw new ArchiveException('The archive is larger than '.self::megabytes(ArchiveFormat::MAX_ARCHIVE_BYTES).'.', 'archive.errors.tooLarge', ['size' => self::megabytes(ArchiveFormat::MAX_ARCHIVE_BYTES)]);
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new ArchiveException('The file is not a readable zip archive.', 'archive.errors.notZip');
        }

        $archive = new self($zip);
        $archive->vetEntries();

        return $archive;
    }

    /**
     * Whether the archive has a file entry of this name.
     */
    public function has(string $name): bool
    {
        return isset($this->sizes[$name]);
    }

    /**
     * Declared uncompressed size of an entry.
     */
    public function size(string $name): int
    {
        return $this->sizes[$name] ?? 0;
    }

    /**
     * Read and decode a JSON entry, never more than $maxBytes of it.
     *
     * @throws ArchiveException When it is missing, too large, or not valid JSON
     */
    public function json(string $name, int $maxBytes = ArchiveFormat::MAX_JSON_BYTES): mixed
    {
        if (!$this->has($name)) {
            throw new ArchiveException("The archive has no {$name}.", 'archive.errors.missingEntry', ['name' => $name]);
        }

        try {
            return json_decode($this->read($name, $maxBytes), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ArchiveException("{$name} is not valid JSON.", 'archive.errors.badJson', ['name' => $name]);
        }
    }

    /**
     * The content type of an entry, sniffed from its first bytes.
     */
    public function sniffMime(string $name): string
    {
        $stream = $this->stream($name);
        try {
            $head = (string) fread($stream, 4096);
        } finally {
            fclose($stream);
        }

        return (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($head);
    }

    /**
     * Copy an entry to a file the caller chose, stopping at $maxBytes of real output.
     *
     * @throws ArchiveException When the entry produces more than $maxBytes
     */
    public function extractTo(string $name, string $destPath, int $maxBytes): void
    {
        $in = $this->stream($name);
        $out = fopen($destPath, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException("Could not write {$destPath}");
        }

        try {
            $this->copyCapped($in, $out, min($maxBytes, $this->size($name)), $name);
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($destPath);

            throw $e;
        } finally {
            fclose($in);
        }

        fclose($out);
    }

    public function close(): void
    {
        $this->zip->close();
    }

    /**
     * Reject the archive on its central directory alone.
     *
     * @throws ArchiveException
     */
    private function vetEntries(): void
    {
        if ($this->zip->numFiles > ArchiveFormat::MAX_ENTRIES) {
            throw new ArchiveException('The archive has more than '.ArchiveFormat::MAX_ENTRIES.' entries.', 'archive.errors.tooManyEntries', ['count' => ArchiveFormat::MAX_ENTRIES]);
        }

        $total = 0;
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i, ZipArchive::FL_UNCHANGED);
            if ($stat === false) {
                throw new ArchiveException('The archive index cannot be read.', 'archive.errors.notZip');
            }

            $name = (string) $stat['name'];
            $this->vetName($name);
            $this->vetSize($name, (int) $stat['size'], (int) $stat['comp_size']);

            if ($stat['encryption_method'] !== ZipArchive::EM_NONE) {
                throw new ArchiveException('The archive contains an encrypted entry.', 'archive.errors.encrypted');
            }

            $total += (int) $stat['size'];
            if ($total > ArchiveFormat::MAX_TOTAL_BYTES) {
                throw new ArchiveException('The archive unpacks to more than '.self::megabytes(ArchiveFormat::MAX_TOTAL_BYTES).'.', 'archive.errors.unpacksTooLarge', ['size' => self::megabytes(ArchiveFormat::MAX_TOTAL_BYTES)]);
            }

            if (!str_ends_with($name, '/')) {
                $this->sizes[$name] = (int) $stat['size'];
            }
        }

        if (!$this->has(ArchiveFormat::MANIFEST)) {
            throw new ArchiveException('The archive has no manifest.json, so it is not a Lexicon blog archive.', 'archive.errors.noManifest');
        }
    }

    /**
     * @throws ArchiveException
     */
    private function vetName(string $name): void
    {
        $segments = explode('/', rtrim($name, '/'));

        if (preg_match(self::SAFE_NAME, $name) !== 1
            || in_array('..', $segments, true)
            || in_array('.', $segments, true)) {
            throw new ArchiveException('The archive contains an unsafe file path.', 'archive.errors.unsafePath');
        }
        if (isset($this->sizes[$name])) {
            throw new ArchiveException('The archive contains the same file twice.', 'archive.errors.duplicateEntry');
        }
    }

    /**
     * @throws ArchiveException
     */
    private function vetSize(string $name, int $size, int $compressed): void
    {
        $limit = str_starts_with($name, 'media/') ? ArchiveFormat::MAX_IMAGE_BYTES : ArchiveFormat::MAX_JSON_BYTES;
        if ($size > $limit) {
            throw new ArchiveException("{$name} is larger than ".self::megabytes($limit).'.', 'archive.errors.entryTooLarge', ['name' => $name, 'size' => self::megabytes($limit)]);
        }

        if ($size > ArchiveFormat::RATIO_FLOOR_BYTES && $size > $compressed * ArchiveFormat::MAX_RATIO) {
            throw new ArchiveException("{$name} expands too far to be a genuine file.", 'archive.errors.zipBomb', ['name' => $name]);
        }
    }

    private function read(string $name, int $maxBytes): string
    {
        $in = $this->stream($name);
        $out = fopen('php://temp', 'w+b');

        try {
            $this->copyCapped($in, $out, min($maxBytes, $this->size($name)), $name);
            rewind($out);

            return (string) stream_get_contents($out);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /**
     * @param  resource  $in
     * @param  resource  $out
     *
     * @throws ArchiveException
     */
    private function copyCapped($in, $out, int $maxBytes, string $name): void
    {
        $written = 0;
        while (!feof($in)) {
            $chunk = fread($in, 65536);
            if ($chunk === false) {
                throw new ArchiveException("{$name} could not be read from the archive.", 'archive.errors.unreadable', ['name' => $name]);
            }

            $written += strlen($chunk);
            if ($written > $maxBytes) {
                throw new ArchiveException("{$name} unpacks to more than its declared size allows.", 'archive.errors.zipBomb', ['name' => $name]);
            }
            fwrite($out, $chunk);
        }
    }

    /**
     * @return resource
     */
    private function stream(string $name)
    {
        $stream = $this->has($name) ? $this->zip->getStream($name) : false;
        if ($stream === false) {
            throw new ArchiveException("{$name} could not be read from the archive.", 'archive.errors.unreadable', ['name' => $name]);
        }

        return $stream;
    }

    private static function megabytes(int $bytes): string
    {
        return intdiv($bytes, 1024 * 1024).' MB';
    }
}
