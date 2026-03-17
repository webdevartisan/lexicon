<?php

namespace Framework\View;

interface ViewNameResolverInterface
{
    /**
     * Resolve an input template reference to a safe, normalized relative path.
     *
     * Accepts:
     * - null/'' for inferred template
     * - dot notation: public.home.index
     * - legacy relative paths: areas/public/Home/index.lex.php
     */
    public function resolveToRelativePath(?string $template, RouteContext $ctx): string;
}
