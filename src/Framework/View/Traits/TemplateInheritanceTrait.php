<?php

declare(strict_types=1);

namespace Framework\View\Traits;

use Framework\Exceptions\NotFoundException;

trait TemplateInheritanceTrait
{
    private function processInheritance(string $code): string
    {
        if (preg_match('#^{% extends "(?<template>.*)" %}#', $code, $matches) !== 1) {
            return $code;
        }

        $layoutRef = 'layouts/'.DIRECTORY_SEPARATOR.$matches['template'];

        $basePath = $this->themes->resolveView(
            $this->viewResolver->resolveToRelativePath($layoutRef, $this->routeContext)
        );

        if (!$basePath) {
            throw new NotFoundException("Base layout '{$matches['template']}' not found.");
        }

        $base = file_get_contents($basePath);
        $blocks = $this->getBlocks($code);

        return $this->replaceYields($base, $blocks);
    }

    private function getBlocks(string $code): array
    {
        preg_match_all("#{% block (?<name>\w+) %}(?<content>.*?){% endblock %}#s", $code, $matches, PREG_SET_ORDER);

        return array_column($matches, 'content', 'name');
    }

    private function replaceYields(string $code, array $blocks): string
    {
        preg_match_all("#{% yield (?<name>\w+) %}#", $code, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match['name'];
            $block = $blocks[$name] ?? '';
            $replacement = strtr($block, ['\\' => '\\\\', '$' => '\$']);
            $pattern = '#\{\%\s*yield\s+'.preg_quote($name, '#').'\s*\%\}#';
            $code = preg_replace($pattern, $replacement, $code);
        }

        return $code;
    }
}
