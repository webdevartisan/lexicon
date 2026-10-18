<?php

declare(strict_types=1);

namespace App\Services\Archive\Foreign;

use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveFormat;

/**
 * Turns another platform's export into an archive this site's own importer can read.
 *
 * The result goes through the same preview and confirm steps as any archive, so
 * validation, slug clashes, sanitising and draft-by-default are not repeated here.
 */
final class ForeignArchiveBuilder
{
    /**
     * Look up a converter by the name the import form sends.
     */
    public function source(string $key): ?ForeignSource
    {
        return match ($key) {
            'wordpress' => new WordPressSource(),
            'ghost' => new GhostSource(),
            default => null,
        };
    }

    /**
     * Write an archive holding the export's posts to $dest and return how many it holds.
     *
     * @throws ArchiveException When the file is not an export or holds no posts
     */
    public function build(ForeignSource $source, string $path, string $siteUrl, string $dest): int
    {
        $posts = $source->posts($path, $siteUrl);
        if ($posts === []) {
            throw new ArchiveException('No posts were found in that file.', 'archive.foreign.empty', ['source' => $source->name()]);
        }

        $zip = new \ZipArchive();
        if ($zip->open($dest, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create {$dest}");
        }

        $zip->addFromString(ArchiveFormat::MANIFEST, $this->json([
            'format' => ArchiveFormat::NAME,
            'format_version' => ArchiveFormat::VERSION,
            'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'source' => ['name' => $source->name(), 'slug' => ''],
            'counts' => ['posts' => count($posts), 'media' => 0],
            'warnings' => [],
        ]));
        $zip->addFromString(ArchiveFormat::POSTS, $this->json($posts));
        $zip->addFromString(ArchiveFormat::MEDIA, '[]');

        if (!$zip->close()) {
            throw new \RuntimeException("Could not write {$dest}");
        }

        return count($posts);
    }

    /**
     * @param  array<mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
