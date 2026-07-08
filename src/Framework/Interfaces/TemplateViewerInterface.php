<?php

declare(strict_types=1);

namespace Framework\Interfaces;

interface TemplateViewerInterface
{
    /**
     * Render a template with the given data.
     *
     * @param  string  $template  Template path or dot-notation name
     * @param  array<string, mixed>  $data  Variables made available to the template
     * @return string Rendered output
     */
    public function render(string $template, array $data = []): string;

    /**
     * Register variables shared with every rendered template.
     *
     * @param  array<string, mixed>  $vars  Variable name => value pairs
     */
    public function addGlobals(array $vars): void;

    /**
     * Report count and total size of compiled view files.
     *
     * @return array<string, int> Keys: 'count', 'size_bytes'
     */
    public function compiledViewStats(): array;

    /**
     * Delete compiled view files older than the given age.
     *
     * @param  int  $maxAgeSeconds  Files older than this are removed
     * @return int Number of files deleted
     */
    public function pruneCompiledViews(int $maxAgeSeconds): int;

    /**
     * Delete all compiled view files.
     *
     * @return array<string, int> Keys: 'deleted', 'failed'
     */
    public function clearCompiledViews(): array;
}
