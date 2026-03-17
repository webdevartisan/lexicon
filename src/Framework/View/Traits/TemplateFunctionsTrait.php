<?php

declare(strict_types=1);

namespace Framework\View\Traits;

trait TemplateFunctionsTrait
{
    /**
     * Note: Distinguish between:
     * - helper_var: injected template globals exposed as variables (e.g. $t).
     * - php_function: real PHP functions (call with a leading backslash).
     *
     * @var array<string, array{type: 'helper_var'|'php_function', html?: bool}>
     */
    private array $allowedTemplateFunctions = [
        't' => ['type' => 'helper_var'],
        'base_url' => ['type' => 'php_function'],
        'locale' => ['type' => 'php_function'],
        'old' => ['type' => 'php_function'],
        'csrf_field' => ['type' => 'php_function', 'html' => true],
        'csrf_token' => ['type' => 'php_function'],
        'strtoupper' => ['type' => 'php_function'],
        // Add more safe helpers here.
    ];

    private function replaceFunctions(string $code): string
    {
        return preg_replace_callback(
            '#{{\s*(?<fn>[a-zA-Z_]\w*)\((?<args>[^}]*)\)\s*(?:\|\s*(?<filter>\w+))?\s*}}#',
            function (array $m): string {
                return $this->compileFunctionCall(
                    $m['fn'] ?? '',
                    $m['args'] ?? '',
                    $m['filter'] ?? null
                );
            },
            $code
        );
    }

    private function compileFunctionCall(string $fn, string $args, ?string $filter): string
    {
        $meta = $this->allowedTemplateFunctions[$fn] ?? null;
        if ($meta === null) {
            throw new \RuntimeException("Template function '{$fn}' is not allowed.");
        }

        $args = trim($args);

        // Build the callable expression.
        if ($meta['type'] === 'helper_var') {
            // Note: This matches your existing middleware that injects 't' as a global.
            $callee = '$'.$fn;
        } else {
            // Note: Force global function resolution and avoid namespace surprises.
            $callee = '\\'.$fn;
        }

        $call = ($args === '') ? "{$callee}()" : "{$callee}({$args})";

        // Note: HTML-returning helpers must be raw by default.
        $isHtmlHelper = (bool) ($meta['html'] ?? false);
        if ($isHtmlHelper && $filter !== 'raw') {
            return "<?= {$call} ?>";
        }

        return $filter === 'raw'
            ? "<?= {$call} ?>"
            : "<?= e({$call}) ?>";
    }
}
