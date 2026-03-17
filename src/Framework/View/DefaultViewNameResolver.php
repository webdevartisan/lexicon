<?php

namespace Framework\View;

use InvalidArgumentException;

final class DefaultViewNameResolver implements ViewNameResolverInterface
{
    /**
     * @param  array<string,string>  $namespaceAreaMap  e.g. ['Admin' => 'admin', 'Dashboard' => 'dashboard', 'Auth' => 'auth']
     */
    public function __construct(
        private array $namespaceAreaMap = [
            'Admin' => 'admin',
            'Dashboard' => 'dashboard',
            'Auth' => 'auth',
        ],
        private string $defaultArea = 'public',
        private string $extension = '.lex.php',
    ) {}

    public function resolveToRelativePath(?string $template, RouteContext $ctx): string
    {
        $template = $template !== null ? trim($template) : null;

        // 1) Infer if missing
        if ($template === null || $template === '') {

            $controller = $ctx->controllerFqcn();
            $action = $ctx->action();

            if ($controller === null || $action === null) {
                throw new InvalidArgumentException('Cannot infer view without controller/action in RouteContext.');
            }

            $area = $this->inferAreaFromControllerFqcn($controller);
            $controllerDir = $this->inferControllerDirFromFqcn($controller);

            return $this->guardAndNormalizeRelativePath(
                "areas/{$area}/{$controllerDir}/{$action}{$this->extension}"
            );
        }

        // 2) Legacy path input (contains slash/backslash or ends with .lex.php)
        if (str_contains($template, '/') || str_contains($template, '\\') || str_ends_with($template, $this->extension)) {
            return $this->guardAndNormalizeRelativePath($template);
        }

        // 3) Dot notation input
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $template)) {
            throw new InvalidArgumentException('Invalid template name format.');
        }

        $parts = array_values(array_filter(explode('.', $template), fn ($p) => $p !== ''));

        // Support either:
        // - public.home.index (area.controller.action)
        // - home.index        (controller.action) -> uses inferred area
        if (count($parts) === 3) {
            [$area, $controller, $action] = $parts;
        } elseif (count($parts) === 2) {
            $area = $this->inferAreaFromControllerFqcn($ctx->controllerFqcn() ?? '');
            [$controller, $action] = $parts;
        } else {
            throw new InvalidArgumentException('Dot notation must be "area.controller.action" or "controller.action".');
        }

        $controllerDir = $this->studly($controller);

        return $this->guardAndNormalizeRelativePath(
            "areas/{$area}/{$controllerDir}/{$action}{$this->extension}"
        );
    }

    private function inferAreaFromControllerFqcn(string $fqcn): string
    {
        // App\Controllers\Admin\XxxController -> "Admin"
        $segments = explode('\\', trim($fqcn, '\\'));
        $key = $segments[count($segments) - 2] ?? '';

        return $this->namespaceAreaMap[$key] ?? $this->defaultArea;
    }

    private function inferControllerDirFromFqcn(string $fqcn): string
    {
        $segments = explode('\\', trim($fqcn, '\\'));
        $class = $segments[count($segments) - 1] ?? 'HomeController';

        $class = preg_replace('/Controller$/i', '', $class) ?: $class;

        return $class;
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', strtolower($value));
        $value = str_replace(' ', '', ucwords($value));

        return $value;
    }

    private function guardAndNormalizeRelativePath(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);

        // Security: we should reject traversal, absolute paths, protocol wrappers, null bytes.
        if ($relative === '' ||
            str_starts_with($relative, '/') ||
            str_contains($relative, "\0") ||
            str_contains($relative, '../') ||
            str_contains($relative, '..\\') ||
            str_contains($relative, '://')
        ) {
            throw new InvalidArgumentException('Unsafe template path.');
        }

        // Normalize multiple slashes
        $relative = preg_replace('#/+#', '/', $relative) ?? $relative;

        // Optional: enforce extension for view files
        if (!str_ends_with($relative, $this->extension)) {
            // d($relative);
            throw new InvalidArgumentException('Template must end with '.$this->extension);
        }

        return ltrim($relative, '/');
    }
}
