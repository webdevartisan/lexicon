<?php

declare(strict_types=1);

namespace App\Services\Archive\Foreign;

use App\Services\Archive\ArchiveException;

/**
 * Reads another platform's export file and returns its posts in the archive's post shape.
 */
interface ForeignSource
{
    /**
     * Platform name shown in the preview.
     */
    public function name(): string;

    /**
     * File extension the export comes in, without the dot.
     */
    public function extension(): string;

    /**
     * Whether the owner has to say where the old site lived.
     */
    public function needsSiteUrl(): bool;

    /**
     * @param  string  $siteUrl  Address of the old site, or an empty string
     * @return list<array<string, mixed>> Posts as produced by ForeignPost::make()
     *
     * @throws ArchiveException When the file is not a usable export
     */
    public function posts(string $path, string $siteUrl): array;
}
