<?php

declare(strict_types=1);

namespace Framework\View;

use Framework\Exceptions\NotFoundException;
use Framework\Exceptions\TemplateRenderException;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Interfaces\ThemeResolverInterface;
use Framework\View\Traits\TemplateCacheTrait;
use Framework\View\Traits\TemplateComponentsTrait;
use Framework\View\Traits\TemplateFunctionsTrait;
use Framework\View\Traits\TemplateInheritanceTrait;
use Framework\View\Traits\TemplatePhpDirectivesTrait;
use Framework\View\Traits\TemplateVariablesTrait;

class TemplateRenderer implements TemplateViewerInterface
{
    use TemplateCacheTrait;
    use TemplateComponentsTrait;
    use TemplateFunctionsTrait;
    use TemplateInheritanceTrait;
    use TemplatePhpDirectivesTrait;
    use TemplateVariablesTrait;

    // ============================================================
    // Properties & Constructor
    // ============================================================

    private array $globals = [];

    /**
     * Directory where compiled template PHP files are stored.
     *
     * We compile templates to disk and include them instead of using eval()
     * to avoid the security risks associated with evaluating arbitrary strings.
     */
    private string $compiledViewPath;

    /**
     * Tracks every source file touched during a single render() call.
     *
     * Includes the root template, all {% include %} partials, and any
     * layout files resolved during inheritance. Used to compute a
     * combined mtime hash so that changing ANY dependency invalidates
     * the compiled cache file automatically.
     *
     * Reset after each render() to prevent cross-request contamination
     * (important because TemplateRenderer is registered as a singleton).
     */
    private array $compilationDependencies = [];

    /**
     * @param  ThemeResolverInterface  $themes  Resolves themed template paths.
     * @param  ViewNameResolverInterface  $viewResolver  Maps template names to relative paths.
     * @param  RouteContext  $routeContext  Current route context for view resolution.
     * @param  string|null  $compiledViewPath
     *                                         Override the default compiled view cache directory.
     *                                         When null, defaults to ROOT_PATH/storage/cache/views.
     *                                         Pass via config/cache.php 'compiled_views_path' for consistency.
     */
    public function __construct(
        private ThemeResolverInterface $themes,
        private ViewNameResolverInterface $viewResolver,
        private RouteContext $routeContext,
        ?string $compiledViewPath = null,
    ) {
        // Fall back to the conventional path if no override is provided.
        $this->compiledViewPath = $compiledViewPath ?? ROOT_PATH.'/storage/cache/views';
    }

    // ============================================================
    // Public API
    // ============================================================

    public function addGlobals(array $vars): void
    {
        $this->globals = array_replace($this->globals, $vars);
    }

    /**
     * Render a template file and return the resulting HTML string.
     *
     * @param  string|null  $template  Template identifier (resolved via ViewNameResolver).
     * @param  array  $data  Variables to expose inside the template.
     * @return string Rendered HTML output.
     *
     * @throws NotFoundException If the template or any include cannot be found.
     * @throws TemplateRenderException If the compiled template throws at runtime.
     */
    public function render(?string $template, array $data = []): string
    {
        // Reset dependencies for this render pass.
        // Critical for singletons: prevents prior render's files leaking into this hash.
        $this->compilationDependencies = [];

        $file = $this->resolveTemplate($template);
        $code = $this->loadTemplateCode($file);

        $code = $this->processInheritance($code);
        $code = $this->processIncludes($code);
        $code = $this->compileCode($code);

        // Compile the template code to a cached PHP file and include it.
        $compiledFile = $this->cacheCompiledTemplate($file, $code);

        $data = $this->mergeGlobals($data);

        return $this->evaluateTemplate($compiledFile, $data, $file);
    }

    /**
     * Delete all compiled view PHP files from the cache directory.
     *
     * Should be called from CacheController when clearing the full application cache,
     * alongside CacheService::clear(), to ensure compiled views are also wiped.
     *
     * @return array{deleted: int, failed: int} Counts of deleted and failed files.
     */
    public function clearCompiledViews(): array
    {
        $files = glob($this->compiledViewPath.'/*.php') ?: [];
        $deleted = 0;
        $failed = 0;

        foreach ($files as $file) {
            @unlink($file) ? $deleted++ : $failed++;
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /**
     * Delete compiled view files older than a given age.
     *
     * Acts as a safety net for any orphaned files that slip past
     * mtime-based invalidation (e.g., files from deleted templates).
     * Called from CacheController::prune() alongside CacheService::pruneExpired().
     *
     * @param  int  $maxAgeSeconds  Files older than this are removed (default: 7 days).
     * @return int Number of files deleted.
     */
    public function pruneCompiledViews(int $maxAgeSeconds = 604800): int
    {
        $files = glob($this->compiledViewPath.'/*.php') ?: [];
        $cutoff = time() - $maxAgeSeconds;
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file) && $deleted++;
            }
        }

        return $deleted;
    }

    // ============================================================
    // Template Resolution & Loading
    // ============================================================

    /**
     * Resolve a template identifier to an absolute file path via the theme system.
     *
     * @throws NotFoundException If no matching file is found in the active theme.
     */
    private function resolveTemplate(?string $template): string
    {
        $relativePath = $this->viewResolver->resolveToRelativePath($template, $this->routeContext);
        $file = $this->themes->resolveView($relativePath);
        if (!$file) {
            throw new NotFoundException("Template '$relativePath' not found.");
        }

        return $file;
    }

    /**
     * Read raw template source code from disk and register the file as a dependency.
     *
     * Registering here ensures the root template's mtime is included in the
     * compiled cache key, so direct edits to the template trigger recompilation.
     */
    private function loadTemplateCode(string $file): string
    {
        // Track this file so its mtime contributes to the compiled cache hash.
        $this->compilationDependencies[] = $file;

        return file_get_contents($file);
    }

    /**
     * Entry point for resolving all {% include %} directives in template code.
     */
    private function processIncludes(string $code): string
    {
        return $this->loadIncludesThemed($code);
    }

    /**
     * Recursively resolve all {% include "..." %} directives in the template code.
     *
     * Processes nested includes by looping until no directives remain.
     * A depth counter prevents infinite loops caused by circular includes.
     *
     * @param  string  $code  The raw template code to process.
     * @param  int  $maxDepth  Maximum allowed nesting depth (default: 10).
     * @return string Template code with all includes inlined.
     *
     * @throws NotFoundException If an included template file cannot be found.
     * @throws \RuntimeException If the maximum include depth is exceeded.
     */
    private function loadIncludesThemed(string $code, int $maxDepth = 10): string
    {
        $depth = 0;

        // Keep resolving until no include tags remain — handles nested includes.
        while (preg_match('#\{%\s*include\s+".*?"\s*%\}#', $code)) {

            if ($depth >= $maxDepth) {
                throw new \RuntimeException(
                    "Maximum include depth of {$maxDepth} exceeded. ".
                    'Check for circular {% include %} references in your templates.'
                );
            }

            preg_match_all('#\{%\s*include\s+"(?<template>.*?)"\s*%\}#', $code, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $tpl = $match['template'];
                $relativePath = $this->viewResolver->resolveToRelativePath($tpl, $this->routeContext);
                $incPath = $this->themes->resolveView($relativePath);

                if (!$incPath) {
                    throw new NotFoundException("Included template '$tpl' not found.");
                }

                // Register each include as a dependency so changes to any
                // partial invalidate the parent template's compiled cache.
                $this->compilationDependencies[] = $incPath;

                $contents = file_get_contents($incPath);
                $pattern = '#\{\%\s*include\s+"'.preg_quote($tpl, '#').'"\s*\%\}#';
                $code = preg_replace($pattern, $contents, $code, 1);
            }

            $depth++;
        }

        return $code;
    }

    // ============================================================
    // Compilation Passes
    // ============================================================

    /**
     * Run all compilation passes over the raw template source code.
     *
     * Order matters: comments are stripped first so later passes don't
     * accidentally process commented-out directives.
     */
    private function compileCode(string $code): string
    {
        $code = $this->removeComments($code);
        $code = $this->replaceCacheBlocks($code);
        $code = $this->replaceFunctions($code);
        $code = $this->replaceComponents($code);
        $code = $this->replaceVariables($code);
        $code = $this->replacePHP($code);

        return $code;
    }

    // ============================================================
    // Globals & Evaluation
    // ============================================================

    /**
     * Merge global template variables with per-render data.
     *
     * Per-render $data takes priority over globals via array_replace
     * so callers can always override a global value when needed.
     */
    private function mergeGlobals(array $data): array
    {
        return array_replace($this->globals, $data);
    }

    /**
     * Evaluate a compiled template file and return the rendered output.
     *
     * The compiled template is included from disk instead of being evaluated
     * via eval(), which reduces the risk of arbitrary code execution and
     * makes it easier to inspect compiled output when debugging.
     *
     * The include is isolated inside a static closure so that renderer
     * internals ($compiledFile, $data, $prev, etc.) are never visible
     * to the template's variable scope.
     *
     * @param  string  $compiledFile  Absolute path to the compiled PHP file.
     * @param  array  $data  Variables to expose inside the template.
     * @param  string  $originalFile  Original source template path (for error reporting).
     * @return string Rendered HTML output.
     *
     * @throws TemplateRenderException On any error or exception during rendering.
     */
    private function evaluateTemplate(string $compiledFile, array $data, string $originalFile): string
    {
        ob_start();

        $prev = set_error_handler(function (int $severity, string $message, string $errfile, int $errline) use ($originalFile) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $originalFile, $errline);
        });

        try {
            // Isolate the include in a closure — the template only
            // sees $data variables, not renderer internals like $compiledFile.
            $render = function (string $_compiledFile, array $_data): void {
                extract($_data, EXTR_SKIP);
                /** @psalm-suppress UnresolvableInclude */
                include $_compiledFile;
            };

            $render($compiledFile, $data);

            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new TemplateRenderException($originalFile, $e->getLine(), 'Compiled view: '.$compiledFile, $e);
        } finally {
            set_error_handler($prev);
        }
    }

    /**
     * Cache compiled template code to a PHP file and return its path.
     *
     * The cache hash is derived from the combined mtime of ALL files involved
     * in producing the output — root template, includes, and layout files.
     * This guarantees that editing any dependency triggers automatic recompilation
     * without leaving orphaned files behind (one compiled file per unique dependency set).
     *
     * @param  string  $sourceFile  Absolute path to the root template source.
     * @param  string  $compiledCode  Fully compiled PHP/HTML code to persist.
     * @return string Absolute path to the compiled cache file.
     */
    private function cacheCompiledTemplate(string $sourceFile, string $compiledCode): string
    {
        if (!is_dir($this->compiledViewPath)) {
            mkdir($this->compiledViewPath, 0755, true);
        }

        // Build a combined mtime fingerprint from every file that contributed
        // to this compiled output. Changing any dependency changes the hash,
        // causing a new compiled file to be written and the stale one to be
        // naturally abandoned (pruned by pruneCompiledViews() or clearCompiledViews()).
        $dependencies = array_unique($this->compilationDependencies);
        $mtimeKey = implode(':', array_map(
            fn (string $f) => $f.'@'.(int) filemtime($f),
            $dependencies
        ));
        $hash = sha1($sourceFile.':'.$mtimeKey);
        $compiledFile = $this->compiledViewPath.'/'.$hash.'.php';

        if (!is_file($compiledFile)) {
            $header = "<?php\n"
                ."// Compiled view generated by TemplateRenderer\n"
                ."// Source:    {$sourceFile}\n"
                .'// Dependencies: '.implode(', ', $dependencies)."\n"
                .'// Compiled:  '.date('Y-m-d H:i:s')."\n"
                ."?>\n"; // Trailing newline prevents compiled code fusing to the closing tag.

            // Atomic write: write to a PID-unique tmp file then rename.
            // rename() is atomic on most filesystems, preventing partial reads
            // from concurrent requests hitting the cache simultaneously.
            $tmpFile = $compiledFile.'.tmp.'.getmypid();
            file_put_contents($tmpFile, $header.$compiledCode, LOCK_EX);
            rename($tmpFile, $compiledFile);
        }

        return $compiledFile;
    }

    /**
     * Return basic statistics about the compiled view cache directory.
     *
     * Used by CacheManagementService to include compiled view counts
     * in the admin dashboard stats without exposing the internal path.
     *
     * @return array{count: int, size_bytes: int}
     */
    public function compiledViewStats(): array
    {
        $files = glob($this->compiledViewPath.'/*.php') ?: [];

        return [
            'count' => count($files),
            'size_bytes' => (int) array_sum(array_map('filesize', $files)),
        ];
    }
}
