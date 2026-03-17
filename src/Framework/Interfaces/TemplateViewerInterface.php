<?php

namespace Framework\Interfaces;

interface TemplateViewerInterface
{
    public function render(string $template, array $data = []): string;

    public function addGlobals(array $vars): void;

    public function compiledViewStats(): array;

    public function pruneCompiledViews(int $maxAgeSeconds): int;

    public function clearCompiledViews(): array;
}
